<?php
namespace Tessi\JobBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

//Entities
use Tessi\JobBundle\Entity\Job;

class JobRunCommand extends ContainerAwareCommand
{
		const DEFAULT_MAX_TASK_PROCESS = 1;
		/**
		 * function
		 */
		protected function configure()
		{
		    $this
		        ->setName('job:run')
		        ->setDescription('Executes tasks')
		        ->addArgument(
		            'jobCodeName',
		            InputArgument::OPTIONAL,
		            'What job to launch ?'
		        )
						->addOption(
								'maxTasks',
								null,
								InputOption::VALUE_REQUIRED,
								'Max tasks process to launch each time (default 1)?'
						)
		        ->addOption(
		           'debug',
		           null,
		           InputOption::VALUE_NONE,
		           'Debug mode'
		        );
		}

		protected function execute(InputInterface $input, OutputInterface $output)
		{
				$em    = $this->getContainer()->get('doctrine')->getEntityManager('job');
				$conn  = $this->getContainer()->get('doctrine.dbal.job_connection');
				$limit = self::DEFAULT_MAX_TASK_PROCESS;
				if ($maxTasks = $input->getOption('maxTasks')) {
						$limit = $maxTasks;
				}

				$time  = date('1970-01-01 H:i:s');
				$sql = "
						SELECT t.id FROM jobbundletask t
						INNER JOIN jobbundlejob j ON j.id = t.job_id
						WHERE t.startdate is null
						AND t.enddate IS NULL
						AND j.currentRunningCount < j.maxconcurrenttasks";

				if ($code = $input->getArgument('jobCodeName')) {
						$sql .= " AND j.code = '" . $code. "'";
				}

				$sql .= "
						AND (
								(
									j.endTaskRestrictionDate > j.startTaskRestrictionDate
								AND j.startTaskRestrictionDate < '$time'
								AND j.endTaskRestrictionDate   > '$time'
								)
							OR 	(
									j.endTaskRestrictionDate < j.startTaskRestrictionDate
								AND NOT (	j.startTaskRestrictionDate < '$time'
										AND j.endTaskRestrictionDate   > '$time')
								)
							)
						ORDER BY t.executiondate ASC
						LIMIT $limit
				  	";

				$tasksIds = $conn->fetchAll($sql);

				$ids = array();
				foreach($tasksIds as $task) {
						$ids[] = $task['id'];
				}

				if(count($ids) == 0) {
						$message = '----NO TASKS TO EXECUTE';
						if ($code) {
								$message .= ' FOR JOB ' . $code;
						}
						$output->writeln($message);
						return;
				}

		  	//On cherche les taches à executer
				$foundTasks = $em->getRepository('JobBundle:Task')->createQueryBuilder('t')
													->andWhere('t.id IN (:ids)')
									        ->setParameter('ids', $ids)
													->getQuery()->getResult();

				$taskToExecute = array();

				$startDate = new \DateTime('now');

		    //Lock task for other executions
				foreach($foundTasks as $task) {
						$taskToExecute[] = $task;
						$task->setStartDate($startDate);
		        $job = $task->getJob();

		        $this->incrementJobRunning($job);
		        $em->persist($job);
				}

				$em->flush();

				$output->writeln('-------------------------------------------');
				$output->writeln('----EXECUTION STARTING ON ' . count($taskToExecute) . ' TASK(S) [' . $startDate->format('Y-m-d H:i:s') . ']');

				foreach($taskToExecute as $task) {

						try {
								$output->writeln('--------Execution of task #' . $task->getId());
								$this->executeTache($task, $output);
						} catch(\Exception $e) {

						$output->writeln('--------ERROR (catched Exception) ON TASK #' . $task->getId());
						$task->setErrorMessage(	$e->getMessage() . chr(10)
												. ' IN ' .      $e->getFile() . chr(10)
												. ' ON LINE ' . $e->getLine() . chr(10)
												. ' STACKSTRACE : ' .chr(10)
												. $e->getTraceAsString());
						}

						$task->setEndDate(new \DateTime('now'));
						$job = $task->getJob();

	          $this->decrementJobRunning($job);
	          $em->persist($job);

						// $em->persist($task);
				}
				$em->flush();

				$endDate = new \DateTime('now');
				$output->writeln('----END OF EXECUTION OF ' . count($taskToExecute) . ' TASK(S) [' . $endDate->format('Y-m-d H:i:s') . ']');

    }

		protected function executeTache($task, &$output = null)
		{
				$scriptNamespace = $task->getJob()->getNamespace();

				if(!class_exists($scriptNamespace)) {
					$output->writeln('--------SCRIPT $scriptNamespace class do not exists.');
					throw new \Exception("$scriptNamespace class do not exists.");
				}

				$output->writeln('--------SCRIPT ' . $task->getJob()->getNamespace() . ' ON TASK #' . $task->getId());

				$script = new $scriptNamespace($this->getContainer());
				$input  =  (array) json_decode($task->getInput());
				$script->executeTask($input, $scriptNamespace);
		}

		protected function updateCurrentRunningCount($job, $delta)
		{
				$conn = $this->getContainer()->get('doctrine.dbal.job_connection');
				$id   = $job->getId();
				$sql  = "UPDATE jobbundlejob SET currentRunningCount = currentRunningCount + $delta WHERE id = $id";
				$conn->query($sql);
		}

		protected function incrementJobRunning($job)
		{
				$this->updateCurrentRunningCount($job, 1);
		}

		protected function decrementJobRunning($job)
		{
				$this->updateCurrentRunningCount($job, -1);
		}
}
