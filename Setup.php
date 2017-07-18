<?php

namespace SV\EmailQueue;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		$this->schemaManager()->createTable('xf_mail_queue_failed', function (Create $table)
		{
			$table->addColumn('mail_id', 'VARBINARY', 20)->primaryKey();
			$table->addColumn('mail_data', 'MEDIUMBLOB');
			$table->addColumn('queue_date', 'int');
			$table->addColumn('fail_count', 'int');
			$table->addColumn('last_fail_date', 'int');
			$table->addColumn('dispatched', 'BIT')->setDefault(0);
			$table->addKey('dispatched');
			$table->addKey('last_fail_date');
		});
	}

	public function upgrade(array $stepParams = [])
	{
		// TODO: Implement upgrade() method.
	}

	public function uninstall(array $stepParams = [])
	{
		// TODO: Implement uninstall() method.
	}
}