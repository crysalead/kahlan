<?php

declare(strict_types=1);

namespace Kahlan\Jit\Patcher;

final class FinalClass
{
    /**
     * The JIT find file patcher.
     *
     * @param  object $loader The autloader instance.
     * @param  string $class  The fully-namespaced class name.
     * @param  string $file   The correponding finded file path.
     * @return string         The patched file path.
     */
    public function findFile($loader, $class, $file)
    {
        return $file;
    }

    /**
     * The JIT patchable checker.
     *
     * @param  string  $class The fully-namespaced class name to check.
     * @return boolean
     */
    public function patchable($class)
    {
        return true;
    }

    /**
     * The JIT patcher.
     *
     * @param  object $node The node instance to patch.
     * @param  string $path The file path of the source code.
     * @return object       The patched node.
     */
    public function process($node, $path = null)
    {
        if ($node->type === 'file') {
            foreach ($node->tree as $child_node) {
                $this->process($child_node);
            }
        }

        if ($node->type !== 'code') {
            return $node;
        }

        preg_match('/final $/',  $node->body, $matches);
        if (!empty($matches)) {
            $node->body = preg_replace('/final $/', '', $node->body);
        }

        return $node;
    }
}
