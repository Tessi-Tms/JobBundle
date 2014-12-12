<?php

namespace Tessi\JobBundle\Job;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

abstract class Job implements ContainerAwareInterface, JobInterface
{
	public function __construct(ContainerInterface $container = NULL)
	{
		$this->setContainer($container);
	}

	public function createTasks()
	{
	}

	public function executeTask($input, $scriptNamespace)
	{
	}

	protected function getContainer()
	{
		return $this->container;
	}

	public function setContainer(ContainerInterface $container = NULL)
	{
		$this->container = $container;
	}

}
