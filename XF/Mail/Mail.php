<?php

namespace SV\EmailQueue\XF\Mail;

class Mail extends XFCP_Mail
{
	public function send(\Swift_Transport $transport = null)
	{
		if (\XF::options()->sv_emailqueue_force && !in_array($this->templateName, \XF::options()->sv_emailqueue_exclude))
		{
			return $this->queue();
		}

		$message = $this->getSendableMessage();
		if (!$message->getTo())
		{
			return false;
		}
		$sent = $this->mailer->send($message, $transport);

		if ($sent)
		{
			return $sent;
		}

		return $this->mailer->queueFailed($message);
	}
}