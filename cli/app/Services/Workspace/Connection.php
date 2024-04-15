<?php declare(strict_types=1);

namespace App\Services\Workspace;

use Evenement\{EventEmitterInterface, EventEmitterTrait};
use JsonException;
use React\{Socket\ConnectionInterface, Stream\Util};

use Symfony\Component\Console\Output\OutputInterface;
use function bin2hex;
use function json_decode, json_encode, mb_strlen, mb_substr, pack, unpack;

use const JSON_THROW_ON_ERROR;

class Connection implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected string $payload = '';

    protected ?int $bytes_to_read = null;

    public function __construct(protected ConnectionInterface $connection, protected OutputInterface $output)
    {
        $this->connection->on('data', $this->handleData(...));

        Util::forwardEvents($this->connection, $this, ['error']);
    }

    protected function handleData(string $data): void
    {
        if ($this->bytes_to_read === null) {
            $this->payload .= $data;
            if (mb_strlen($this->payload) < 4) {
                return;
            }
            $this->bytes_to_read = unpack('N', mb_substr($this->payload, 0, 4))[1];
            $data = mb_substr($this->payload, 4);
            $this->payload = '';
            // @todo set timer on loop to reset message if we never get the full payload?
            $this->handleData($data);
        } else {
            $bytes_to_read = $this->bytes_to_read - mb_strlen($data);
            $next_data = null;
            if ($bytes_to_read < 0) {
                $next_data = mb_substr($data, $this->bytes_to_read);
                $data = mb_substr($data, 0, $this->bytes_to_read);
                $bytes_to_read = 0;
            }
            $this->payload .= $data;
            if ($bytes_to_read === 0) {
                try {
                    $message = json_decode($this->payload, true, flags: JSON_THROW_ON_ERROR);
                    $this->emit('message', [$message]);
                    // if any extra data is found after message we handle it
                } catch (JsonException $e) {
                    $this->emit('message-error', [$e]);
                }
                $this->bytes_to_read = null;
                $this->payload = '';
                if ($next_data !== null) {
                    $this->output->writeln('Next data: ' . $next_data);
                    $this->handleData($data);
                }
            } else {
                $this->bytes_to_read = $bytes_to_read;
            }
        }
    }

    public function send(array $data, bool $end = false): void
    {
        $data = json_encode($data, JSON_THROW_ON_ERROR);
        $data = pack('N', mb_strlen($data)) . $data;
        $this->connection->write($data);
        if ($end) {
            $this->connection->end();
        }
    }

    public function sendSuccess(?array $data = null): void
    {
        $this->send([
            'success' => true,
            'data' => $data
        ], true);
    }

    public function sendError(string $message, string|int $code, array $context = []): void
    {
        $this->send([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'context' => $context
        ]);
    }

    // @todo success and error writes with wrapper
}
