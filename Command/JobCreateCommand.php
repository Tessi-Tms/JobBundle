<?php
namespace Tessi\JobBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

//Entities
use Tessi\JobBundle\Entity\Job;

class JobCreateCommand extends ContainerAwareCommand
{
		/**
		 * function
		 */
    protected function configure()
    {
        $this
            ->setName('job:create')
            ->setDescription('Executes tasks creation. Used in OS cron to schedule jobs.')
            ->addArgument(
                'jobCodeName',
                InputArgument::OPTIONAL,
                'What job to schedule ?'
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
				$em = $this->getContainer()->get('doctrine')->getEntityManager('job');
				$currentTime = new \DateTime(date('1970-01-01 H:i:s'));

				// On cherche les taches à executer
				// Le schedule du job lance cette commande afin de générer les tâches du job
				// Inutile de regarder si on est dans une tranche de date quelconque, on fait confiance au cron système
				// En revanche on conserve la vérification du flag isActive
				// afin de permettre de désactiver le job en bdd sans modifier le cron (temporairement)
				$jobsToExecute = $em->getRepository('JobBundle:Job')->createQueryBuilder('j')
									// ->andWhere('j.startRangeDate < :currentDate')
									// ->andWhere('j.endRangeDate > :currentDate')
									->andWhere('j.isActive = 1')
									// ->setParameter('currentDate', $currentTime)
									//->orderBy('j.executionDate', 'ASC')
									//->setMaxResults(1)
									->getQuery()->getResult();

				foreach($jobsToExecute as $job) {
						try {
							$this->executeCreateJobTask($job, $output);
						} catch(\Exception $e) {
							$output->writeln('--------ERROR (catched Exception) ON JOB #' . $job->getId());
						}
				}
		}

		protected function executeCreateJobTask($job, &$output = null)
		{
				$scriptNamespace = $job->getNamespace();

				if(!class_exists($scriptNamespace)) {
						$output->writeln('--------SCRIPT $scriptNamespace class do not exists.');
						throw new \Exception("$scriptNamespace class do not exists.");
				}

				$output->writeln('--------TASKS CREATION ON JOB ' . $job->getCode() . ' DATE : ' . date('Y-m-d H:i:s'));

				$script = new $scriptNamespace($this->getContainer());
				$script->createTasks();
		}
}
