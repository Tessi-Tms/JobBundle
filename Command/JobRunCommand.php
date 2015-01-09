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
						$sql .= " AND j.code = '$code'";
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
						FOR UPDATE
				  	";

				$tasksIds = $conn->fetchAll($sql);

				// FLEB 06/01/2015: Changement de méthode vers native PHP
				/*$ids = array();
				foreach($tasksIds as $task) {
						$ids[] = $task['id'];
				}*/
				$ids = array_column($tasksIds, 'id');

				if(count($ids) == 0) {
						$message = '--NO TASKS TO EXECUTE';
						if ($code) {
								$message .= ' FOR JOB ' . $code;
						}
						$output->writeln($message);
						return;
				}

				$taskToExecute = array();
				$startDate = new \DateTime('now');

				// FLEB 06/01/2015: Réfection de la méthode de prise en compte des tâches
				// 									Ajout de la transaction et des blocs try/catch
				$conn->beginTransaction();
				try {
						//Lock task for other executions
						foreach($ids as $id) {

								// FLEB 07/01/2014: remplacement des màj d'objets par bulk UPDATE
								// On met à jour une seule fois la date de début identique pour toutes les tâches ici (cf. plus loin par tâche)
								$updateSql = 'UPDATE Tessi\JobBundle\Entity\Task t SET t.startDate = :startDate WHERE t.id = :id AND t.startDate IS NULL AND t.endDate IS NULL';
								$query = $em->createQuery($updateSql)
												->setParameter('startDate', $startDate->format('Y-m-d H:i:s'))
												->setParameter('id', $id);

								$result = $query->execute();

								// Si la mise à jour est effecttuée, on prend la tâche sinon on l'ignore (un autre job:run l'a sans doute prise entre-temps)
								if($result = 1) {
										//On cherche les taches à executer
									  $query = $em->getRepository('JobBundle:Task')->createQueryBuilder('t')
														->andWhere('t.id = :id')
												 		->setParameter('id', $id)
														->getQuery();
										$taskToExecute[] = $query->getSingleResult();

										$this->incrementJobRunning($conn, $id);
								}
						}
						$em->flush();
						$conn->commit();

				} catch (Exception $e) {
						$conn->rollback();
						$output->writeln('----Exception (Begin of task update) on task #' . $task->getId());
						$task->setErrorMessage(	$e->getMessage() . chr(10)
										. ' IN ' .      $e->getFile() . chr(10)
										. ' ON LINE ' . $e->getLine() . chr(10)
										. ' STACKSTRACE : ' .chr(10)
										. $e->getTraceAsString());
				    throw $e;
				}

				$output->writeln('--BEGIN OF ' . count($taskToExecute) . ' TASK(S) [' . $startDate->format('Y-m-d H:i:s') . ']');
var_dump($taskToExecute);
				foreach($taskToExecute as $task) {

						try {
								$startDate = new \DateTime('now');
								$task->setStartDate($startDate);
								$em->flush();
								$this->executeTache($task, $output);
						} catch(\Exception $e) {

								$output->writeln('------Exception (Script) on task #' . $task->getId());
								$task->setErrorMessage(	$e->getMessage() . chr(10)
												. ' IN ' .      $e->getFile() . chr(10)
												. ' ON LINE ' . $e->getLine() . chr(10)
												. ' STACKSTRACE : ' .chr(10)
												. $e->getTraceAsString());
						}

						// FLEB 06/01/2015: Ajout de la transaction et des blocs try/catch + mutualisation de plusieurs new \DateTime('now')
						$endDate = new \DateTime('now');
						$conn->beginTransaction();
						try {
								$task->setEndDate($endDate);
								$job = $task->getJob();

			          $this->decrementJobRunning($conn, $job->getId());
			          $em->flush();
								$conn->commit();

						} catch (Exception $e) {
								$conn->rollback();
								$output->writeln('------Exception (End of task update) on task #' . $task->getId());
								$task->setErrorMessage(	$e->getMessage() . chr(10)
												. ' IN ' .      $e->getFile() . chr(10)
												. ' ON LINE ' . $e->getLine() . chr(10)
												. ' STACKSTRACE : ' .chr(10)
												. $e->getTraceAsString());
						}
				}

				$output->writeln('--END OF ' . count($taskToExecute) . ' TASK(S) [' . $endDate->format('Y-m-d H:i:s') . ']');

    }

		protected function executeTache($task, &$output = null)
		{
				$scriptNamespace = $task->getJob()->getNamespace();

				if(!class_exists($scriptNamespace)) {
					$output->writeln('------$scriptNamespace class do not exists.');
					throw new \Exception("$scriptNamespace class do not exists.");
				}

				$output->writeln('------Task #' . $task->getId() . ' Executing ' . $task->getJob()->getNamespace());

				$script = new $scriptNamespace($this->getContainer());
				$input  =  (array) json_decode($task->getInput());
				$script->executeTask($input, $scriptNamespace);
		}

		protected function updateCurrentRunningCount($conn, $job_id, $delta)
		{
				$sql  = "UPDATE jobbundlejob SET currentRunningCount = currentRunningCount + $delta WHERE id = $job_id";
				$conn->query($sql);
		}

		protected function incrementJobRunning($conn, $job_id)
		{
				$this->updateCurrentRunningCount($conn, $job_id, 1);
		}

		protected function decrementJobRunning($conn, $job_id)
		{
				$this->updateCurrentRunningCount($conn, $job_id, -1);
		}
}
