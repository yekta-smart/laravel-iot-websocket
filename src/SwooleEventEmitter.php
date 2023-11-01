<?php

namespace YektaSmart\IotServer\Websocket;

use Exception;
use YektaSmart\IotServer\Contracts\IEventEmitter;
use Swoole\Table;

class SwooleEventEmitter implements IEventEmitter
{
	public function __construct(protected Table $listeners, protected string $prefix)
	{
	}

	/**
	 * @param callable(IClient $client, callable($message) $reply):mixed $cb
	 */
	public function on(string $event, string|callable $cb): void
	{
		$this->insureCallableIsSerializable($cb);
		if ($this->listenerExist($event, $cb)) {
			return;
		}
		$key = $this->findFirstKey($event);
		$this->listeners->set($key, array(
			'once' => 0,
			'cb' => $cb,
			'timeout' => 0,
		));
	}

	/**
	 * @param callable(IClient $client, callable($message) $reply):mixed $cb
	 */
	public function once(string $event, string|callable $cb, ?int $timeout = null): void
	{
		$this->insureCallableIsSerializable($cb);
		if ($this->listenerExist($event, $cb)) {
			return;
		}
		$key = $this->findFirstKey($event);
		$this->listeners->set($key, array(
			'once' => 1,
			'cb' => $cb,
			'timeout' => intval($timeout),
		));
	}

	/**
	 * @param callable $cb
	 */
	public function off(string $event, string|callable|null $cb = null): void
	{
		$this->insureCallableIsSerializable($cb);

		$keys = $this->findListeners($event, $cb);
		foreach ($keys as $key) {
			$this->listeners->del($key);
		}
	}

	/**
	 * @param mixed[] $args
	 */
	public function emit(string $event, mixed ...$args): bool
	{
		$keys = $this->findListeners($event, null);
		if (!$keys) {
			return false;
		}
		$deletes = [];
		foreach ($keys as $key) {
			$row = $this->listeners->get($key);
			if (!$row) {
				continue;
			}
			$this->call($row['cb'], $args);
			if ($row['once']) {
				$deletes[] = $key;
			}
		}
		foreach ($deletes as $key) {
			$this->listeners->del($key);
		}

		return true;
	}

	public function clearExpired(): void
	{
		$keys = [];
		$now = time();
		$prefix = $this->prefix . '-';
		$prefixLen = strlen($prefix);
		foreach ($this->listeners as $key => $row) {
			if (substr($key, 0, $prefixLen) === $prefix) {
				if ($row['timeout'] and $row['timeout'] < $now) {
					$keys[] = $key;
				}
			}
		}
		foreach ($keys as $key) {
			$this->listeners->del($key);
		}

	}


	protected function insureCallableIsSerializable($cb): void {
		if (!is_string($cb)) {
			throw new Exception("callback is not serializable");
		}
	}
	protected function findFirstKey(string $event): string {
		for ($x = 0; $x < 1024; $x++) {
			$rowKey = $this->prefix . '-' . $event . "-" . $x;
			if (!$this->listeners->exists($rowKey)) {
				return $rowKey;
			}
		}
		throw new Exception("Cannot find any available key");
	}

	protected function findListeners(string $event, ?string $cb): array
	{
		$prefix = $this->prefix . '-' . $event . "-";
		$prefixLen = strlen($prefix);
		$keys = [];
		foreach ($this->listeners as $key => $row) {
			if (substr($key, 0, $prefixLen) === $prefix) {
				if ($cb == null or $row['cb'] == $cb) {
					$keys[] = $key;
				}
			}
		}
		return $keys;
	}

	protected function listenerExist(string $event, ?string $cb): bool
	{
		return !empty($this->findListeners($event, $cb));
	}

	protected function call(string $cb, array $args): mixed {
		$type = "";
		$at = strpos($cb, "@");
		if ($at !== false) {
			$cb = explode("@", $cb, 2);
			$type = "method";
		} else {
			$at = strpos($cb, "::");
			if ($at !== false) {
				$cb = explode("::", $cb, 2);
				$type = "static-method";
			} else {
				$type = "function";
			}
		}
		if ($type == "method") {
			$cb[0] = app()->make($cb[0]);
		}
		return call_user_func_array($cb, $args);
	}
}
