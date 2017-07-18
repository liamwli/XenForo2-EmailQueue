<?php

namespace SV\EmailQueue\Option;

use XF\Entity\Option;
use XF\Entity\Template;
use XF\Option\AbstractOption;

class EmailTemplates extends AbstractOption
{
	protected static $defaultIgnoreTemplates = [
		'two_step_login_backup', 'two_step_login_email', "user_email_confirmation", "user_lost_password", "user_lost_password_reset"
	];

	public static function renderOption(Option $option, array $htmlParams)
	{
		$masterStyle = \XF::repository('XF:Style')->getMasterStyle();
		$emailTemplates = \XF::repository('XF:Template')->findEffectiveTemplatesInStyle($masterStyle, 'email')->fetch();

		return self::getTemplate('sv_emailqueue_option_email_templates', $option, $htmlParams, [
			'selectedTemplates' => $emailTemplates->filter(function (Template $template) use ($option)
			{
				return in_array($template->title, $option->option_value);
			}),
			'additionalTemplates' => $emailTemplates->filter(function (Template $template) use ($option)
			{
				return !in_array($template->title, $option->option_value);
			})
		]);
	}

	public static function validateOption(array &$value, Option $option)
	{
		$existingTemplates = isset($value['existing_templates']) ? $value['existing_templates'] : [];
		$newTemplates = isset($value['new_templates']) ? $value['new_templates'] : [];

		$selectedTemplates = array_merge($existingTemplates, $newTemplates);

		if (!$selectedTemplates)
		{
			$value = [];

			return true;
		}

		$masterStyle = \XF::repository('XF:Style')->getMasterStyle();
		$emailTemplateTitles = \XF::repository('XF:Template')->findEffectiveTemplatesInStyle($masterStyle, 'email')->pluckFrom('title')->fetch()->toArray();

		$value = [];

		foreach ($selectedTemplates AS $selectedTemplate)
		{
			if (in_array($selectedTemplate, $emailTemplateTitles))
			{
				$value[] = $selectedTemplate;
			}
		}

		return true;
	}
}