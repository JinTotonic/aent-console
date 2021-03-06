<?php


namespace TheAentMachine\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use TheAentMachine\Aenthill\Aenthill;
use TheAentMachine\Aenthill\CommonEvents;
use TheAentMachine\Exception\LogLevelException;
use TheAentMachine\Helper\AentHelper;
use TheAentMachine\Helper\LogLevelConfigurator;

abstract class AbstractEventCommand extends Command
{
    /** @var LoggerInterface */
    protected $log;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var AentHelper */
    private $aentHelper;

    abstract protected function getEventName(): string;

    abstract protected function executeEvent(?string $payload): ?string;

    protected function configure()
    {
        $this
            ->setName($this->getEventName())
            ->setDescription('Handle the "' . $this->getEventName() . '" event')
            ->addArgument('payload', InputArgument::OPTIONAL, 'The event payload');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws LogLevelException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->aentHelper = new AentHelper($input, $output, $this->getHelper('question'), $this->getHelper('formatter'));
        $logLevelConfigurator = new LogLevelConfigurator($output);
        $logLevelConfigurator->configureLogLevel();

        $this->log = new ConsoleLogger($output);

        $payload = $input->getArgument('payload');
        $this->input = $input;
        $this->output = $output;

        $result = $this->executeEvent($payload);

        // Now, let's send a "reply" event
        if ($result !== null) {
            Aenthill::reply(CommonEvents::REPLY_EVENT, $result);
        }
    }

    /**
     * @return string[]
     */
    public function getAllEventNames(): array
    {
        return array_map(function (AbstractEventCommand $event) {
            return $event->getEventName();
        }, \array_filter($this->getApplication()->all(), function (Command $command) {
            return $command instanceof AbstractEventCommand && !$command->isHidden();
        }));
    }

    protected function getAentHelper(): AentHelper
    {
        if ($this->aentHelper === null) {
            throw new \BadMethodCallException('Function getAentHelper can only be called inside "execute(xxx)" functions.');
        }
        return $this->aentHelper;
    }
}
