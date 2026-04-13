<?php

final class Request
{
    public string $method;
    public string $path;
    /** @var array<string, string> */
    public array $query;
    /** @var array<string, mixed> */
    public array $body;
    /** @var array<string, string> */
    public array $routeParams;

    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $routeParams
     */
    public function __construct(string $method, string $path, array $query = [], array $body = [], array $routeParams = [])
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->routeParams = $routeParams;
    }
}

