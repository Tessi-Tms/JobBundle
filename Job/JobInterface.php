<?php
/*
* This file is part of the JobBundle package.
*
* (c) Tessi Marketing Services <fabrice.lebon@tessi.fr>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Tessi\JobBundle\Job;

/**
* @author Fabrice Lebon <fabrice.lebon@tessi.fr>
*/
Interface JobInterface
{
		/**
		* Create task(s) for the job to be run by the JobBundle.
		*
		*/
		public function createTasks();

		/**
		* Execute tasks recorded for the job one by one.
		*
		*/
		public function executeTask($input, $scriptNamespace);
}
