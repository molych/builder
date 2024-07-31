<?php

namespace DevTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private ?string $currentQuery = null;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $this->currentQuery = $query;
        $query = $this->replaceConditionals($query, $args);
        $query = $this->replacePlaceholders($query, $args);
        return $query;
    }

    private function replaceConditionals(string $query, array &$args): string
    {
        return preg_replace_callback('/\{([^{}]*)\}/', function ($matches) use (&$args) {
            $block = $matches[1];
            if (!end($args)) {
                array_pop($args);
                return '';
            }
            return $block;
        }, $query);
    }

    private function replacePlaceholders(string $query, array &$args): string
    {
        return preg_replace_callback('/\?(?:(#|d|f|a)?)/', function ($matches) use (&$args) {
            $type = $matches[1] ?? null;
            $value = array_shift($args);
            if (is_null($value)) {
                return 'NULL';
            }

            switch ($type) {
                case 'd':
                    return (int)$value;
                case 'f':
                    return (float)$value;
                case 'a':
                    return $this->handleArray($value);
                case '#':
                    return $this->handleIdentifier($value);
                default:
                    return $this->handleDefault($value);
            }
        }, $query);
    }

    private function handleArray($value): string
    {
        if (is_array($value)) {
            // Check if the current query contains 'IN'
            if (strpos($this->currentQuery ?? '', 'IN') !== false) {
                return implode(', ', array_map([$this, 'handleDefault'], $value));
            }
            $elements = [];
            foreach ($value as $key => $item) {
                if (is_int($key)) {
                    $elements[] = $this->handleDefault($item);
                } else {
                    $elements[] = sprintf('%s = %s', $this->handleIdentifier($key), $this->handleDefault($item));
                }
            }
            return implode(', ', $elements);
        }
        return '';
    }

    private function handleIdentifier($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'escapeIdentifier'], $value));
        }

        return $this->escapeIdentifier($value);
    }

    private function handleDefault($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return sprintf('\'%s\'', $this->mysqli->real_escape_string($value));
    }

    private function escapeIdentifier($identifier): string
    {
        return sprintf('`%s`', str_replace('`', '``', $identifier));
    }

    public function skip()
    {
        return null;
    }
}
