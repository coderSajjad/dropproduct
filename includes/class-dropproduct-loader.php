<?php

/**
 * Hook loader.
 *
 * Maintains lists of hooks registered by the plugin and fires them
 * via the WordPress Plugin API.
 *
 * @package DropProduct
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DropProduct_Loader
 *
 * @since 1.0.0
 */
class DropProduct_Loader
{

    /**
     * Registered actions.
     *
     * @var array
     */
    private $actions = array();

    /**
     * Registered filters.
     *
     * @var array
     */
    private $filters = array();

    /**
     * Register an action with WordPress.
     *
     * @param string $hook          WordPress hook name.
     * @param object $component     Object instance.
     * @param string $callback      Method name.
     * @param int    $priority      Hook priority.
     * @param int    $accepted_args Number of accepted arguments.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    /**
     * Register a filter with WordPress.
     *
     * @param string $hook          WordPress hook name.
     * @param object $component     Object instance.
     * @param string $callback      Method name.
     * @param int    $priority      Hook priority.
     * @param int    $accepted_args Number of accepted arguments.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    /**
     * Register all collected hooks with WordPress.
     */
    public function run()
    {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
