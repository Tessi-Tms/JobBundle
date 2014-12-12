<?php

namespace Tessi\JobBundle\Job;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;

abstract class Job implements JobInterface, ContainerAwareInterface
{
	public function __construct(ContainerAwareInterface $container)
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
}
