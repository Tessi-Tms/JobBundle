<?php

namespace Tessi\JobBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tache
 *
 * @ORM\Table(name="jobbundletask")
 * @ORM\Entity(repositoryClass="Tessi\JobBundle\Repository\TaskRepository")
 */
class Task
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="Job")
     */
    private $job;

    /**
     * @var string
     *
     * @ORM\Column(name="input", type="text")
     */
    private $input;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="executionDate", type="datetime")
     */
    private $executionDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="startDate", type="datetime", nullable=true)
     */
    private $startDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="endDate", type="datetime", nullable=true)
     */
    private $endDate;

    /**
     * @var string
     *
     * @ORM\Column(name="errorMessage", type="text", nullable=true)
     */
    private $errorMessage;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set input
     *
     * @param string $input
     * @return Tache
     */
    public function setInput($input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Get input
     *
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Set executionDate
     *
     * @param \DateTime $executionDate
     * @return Tache
     */
    public function setExecutionDate($executionDate)
    {
        $this->executionDate = $executionDate;

        return $this;
    }

    /**
     * Get executionDate
     *
     * @return \DateTime
     */
    public function getExecutionDate()
    {
        return $this->executionDate;
    }

    /**
     * Set startDate
     *
     * @param \DateTime $startDate
     * @return Tache
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Get startDate
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set endDate
     *
     * @param \DateTime $endDate
     * @return Tache
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Get endDate
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Set job
     *
     * @param \Tessi\JobBundle\Entity\Job $job
     * @return Task
     */
    public function setJob(\Tessi\JobBundle\Entity\Job $job = null)
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get job
     *
     * @return \Tessi\JobBundle\Entity\Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Get status
     */
    public function getStatus()
    {
        if($this->getErrorMessage())
            return 'Error';

        $timeout     = $this->getJob()->getTaskTimeOut();
        $dateTimeout = new \DateTime("- $timeout seconds");

        if($this->getStartDate() && !$this->getEndDate() && $this->getStartDate() < $dateTimeout)
            return 'Timeout';

        if($this->getStartDate() && !$this->getEndDate())
            return 'Running';
        if($this->getStartDate() && $this->getEndDate())
            return 'Executed';

        return 'Pending';
    }

    /**
     * Set errorMessage
     *
     * @param string $errorMessage
     * @return Task
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Get errorMessage
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get if running task is timed out
     *
     * @return boolean
     */
    public function isTimedOut($date)
    {
        $timedOut = false;
        if($this->getStartDate() && is_null($this->getEndDate())) {
            $currentDate = new \DateTime('now');
            $timedOut    = ($date->getTimeStamp()
                                    - $this->getStartDate()->getTimeStamp())
                                > $this->getJob()->getTaskTimeOut();
        }

        return $timedOut;
    }
}
