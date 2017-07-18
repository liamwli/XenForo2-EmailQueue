<?php

namespace SV\EmailQueue\XF\Mail;

class Queue extends XFCP_Queue
{
	public function queueFailed(\Swift_Mime_Message $message)
	{
		$toEmails = implode(', ', array_keys($message->getTo()));

		try
		{
			$rawMailObj = serialize($message);
			$mailId = $this->getFailedItemKey($rawMailObj, \XF::$time);
			$this->insertFailedMailQueue($mailId, $rawMailObj, \XF::$time);
		} catch (\Exception $e)
		{
			\XF::logException($e, false, "Exception when attempting to queue failed email for Email to $toEmails: ");
		}

		$jobManager = \XF::app()->jobManager();
		if (!$jobManager->getUniqueJob('MailQueue'))
		{
			try
			{
				$jobManager->enqueueUnique('MailQueue', 'XF\Job\MailQueue', [], false);
			} catch (\Exception $e)
			{
				// need to just ignore this and let it get picked up later;
				// not doing this could lose email on a deadlock
			}
		}

		return true;
	}

	public function run($maxRunTime)
	{
		$s = microtime(true);
		$db = $this->db;
		$mailer = \XF::mailer();
		$options = \XF::options();

		$batchSize = $options->sv_emailqueue_batchsize ? $options->sv_emailqueue_batchsize : null;

		do
		{
			$queue = $this->getQueue($maxRunTime ? $batchSize : null);

			foreach ($queue AS $id => $record)
			{
				if (!$db->delete('xf_mail_queue', 'mail_queue_id = ?', $id))
				{
					// already been deleted - run elsewhere
					continue;
				}

				$message = @unserialize($record['mail_data']);
				if (!($message instanceof \Swift_Mime_Message))
				{
					continue;
				}

				$emailId = $this->getFailedItemKey($record['mail_data'], $record['queue_date']);

				if ($mailer->send($message))
				{
					$this->deliverySuccess();
				}
				else
				{
					$this->deliveryFailure();
				}

				if ($maxRunTime && microtime(true) - $s > $maxRunTime)
				{
					break 2;
				}
			}
		} while ($queue);
	}

	public function runFailed()
	{
		// do not attempt to process email if email is disabled.
		$config = \XF::config();
		if (!$config->enableMail || !$config->enableMailQueue)
		{
			return;
		}

		$latestFailedTime = \XF::repository('SV\EmailQueue:FailedEmailQueue')->getLatestFailedTimestamp();
		if ($latestFailedTime)
		{
			$options = \XF::options();
			$backOffSeconds = $options->sv_emailqueue_backoff * 60;
			if ((!$backOffSeconds || microtime(true) > $latestFailedTime + $backOffSeconds))
			{
				$this->db->beginTransaction();

				$this->db->query('
                    INSERT INTO xf_mail_queue (`mail_data`,`queue_date`)
                    SELECT `mail_data`,`queue_date`
                    FROM xf_mail_queue_failed
                    WHERE dispatched = 0;
                ');
				$this->db->query('
                    UPDATE xf_mail_queue_failed
                    SET dispatched = 1;
                ');

				$this->db->commit();
			}
		}

		$jobManager = \XF::app()->jobManager();
		if (!$jobManager->getUniqueJob('MailQueue'))
		{
			try
			{
				$jobManager->enqueueUnique('MailQueue', 'XF\Job\MailQueue', [], false);
			} catch (\Exception $e)
			{
				// need to just ignore this and let it get picked up later;
				// not doing this could lose email on a deadlock
			}
		}
	}

	protected function insertFailedMailQueue($mailId, $rawMailObj, $queueDate)
	{
		$this->db->query('
            INSERT INTO xf_mail_queue_failed
                (mail_id, mail_data, queue_date, fail_count, last_fail_date)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                dispatched = 0,
                fail_count = fail_count + 1,
                last_fail_date = VALUES(last_fail_date)
        ', [
			$mailId, $rawMailObj, $queueDate, 1, \XF::$time
		]);

		return true;
	}

	function deliveryFailure(\Swift_Mime_Message $mailObj, $mailId, $record)
	{
		// queue the failed email
		$this->insertFailedMailQueue($mailId, $record['mail_data'], $record['queue_date']);
		$toEmails = implode(', ', $mailObj->getTo());
		$failedCount = \XF::repository('SV\EmailQueue:FailedEmailQueue')->getFailedMailCount($mailId);
		$options = \XF::options();
		if ($options->sv_emailqueue_failures_to_error && $failedCount >= $options->sv_emailqueue_failures_to_error)
		{
			// TODO Get the exception.
			$this->deleteFailedMail($mailId);
			\XF::logException(new \Exception(), false, "Abandoning, Email to $toEmails failed: ");
		}
		else if ($options->sv_emailqueue_failures_to_warn && $failedCount >= $options->sv_emailqueue_failures_to_warn)
		{
			\XF::logException(new \Exception(), false, "Queued, Email to $toEmails failed: ");
		}
	}

	public function getFailedItemKey($rawMailObj, $queueDate)
	{
		return sha1($queueDate . $rawMailObj, true);
	}

	protected function deleteFailedMail($mailId)
	{
		$this->db->query('
            DELETE
            FROM xf_mail_queue_failed
            WHERE mail_id = ?
        ', $mailId);
	}
}