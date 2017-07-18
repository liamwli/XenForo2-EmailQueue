<?php

namespace SV\EmailQueue\Repository;

use XF\Mvc\Entity\Repository;

class FailedEmailQueue extends Repository
{
	public function getLatestFailedTimestamp()
	{
		return $this->db()->fetchOne("SELECT max(last_fail_date) FROM xf_mail_queue_failed");
	}

	public function getFailedMailCount($mailId)
	{
		return $this->db()->fetchOne('
            SELECT fail_count
            FROM xf_mail_queue_failed
            WHERE mail_id = ?
        ', $mailId);
	}
}