<?php

namespace AsyncAws\Core\Stream;

use AsyncAws\Core\Exception\InvalidArgument;

/**
 * Convert an iterator into a Stream.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal
 */
final class IterableStream implements ReadOnceResultStream, RequestStream
{
    private $content;

    private function __construct(iterable $content)
    {
        $this->content = $content;
    }

    public static function create($content): IterableStream
    {
        if ($content instanceof self) {
            return $content;
        }
        if (\is_iterable($content)) {
            return new self($content);
        }

        throw new InvalidArgument(sprintf('Expect content to be an iterable. "%s" given.', \is_object($content) ? \get_class($content) : \gettype($content)));
    }

    public function length(): ?int
    {
        return null;
    }

    public function stringify(): string
    {
        if ($this->content instanceof \Traversable) {
            return \implode('', \iterator_to_array($this->content));
        }

        return \implode('', \iterator_to_array((function () {yield from $this->content; })()));
    }

    public function getIterator(): \Traversable
    {
        $dump = [];
        try {
            foreach ($this->content as $content) {
                $dump[] = $content;
                yield $content;
            }
        } catch (\Throwable $e) {
            if ($this->content instanceof \Generator) {
                file_put_contents('tmp/log/async-aws.log', print_r($dump, true) . "\n\n", FILE_APPEND);
                file_put_contents('tmp/log/async-aws.log', $this->content->current() . "\n\n", FILE_APPEND);
            }
            throw $e;
        }
    }

    public function hash(string $algo = 'sha256', bool $raw = false): string
    {
        $ctx = hash_init($algo);
        foreach ($this->content as $chunk) {
            hash_update($ctx, $chunk);
        }

        return hash_final($ctx, $raw);
    }
}
