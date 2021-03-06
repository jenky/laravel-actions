<?php

namespace Lorisleiva\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

class ActionManager
{
    /** @var Collection */
    protected $paths;

    /** @var Collection */
    protected $registeredActions;

    /**
     * Define the default path to use when registering actions.
     */
    public function __construct()
    {
        $this->paths('app'.DIRECTORY_SEPARATOR.'Actions');
        $this->registeredActions = collect();
    }

    /**
     * Define the paths to use when registering actions.
     *
     * @param array|string $paths
     * @return $this
     */
    public function paths($paths): ActionManager
    {
        $this->paths = Collection::wrap($paths)
            ->map(function (string $path) {
                return Str::startsWith($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
            })
            ->unique()
            ->filter(function (string $path) {
                return is_dir($path);
            })
            ->values();

        return $this;
    }

    /**
     * Forbid the action manager to register any actions automatically.
     *
     * @return $this
     */
    public function dontRegister(): ActionManager
    {
        $this->paths = collect();

        return $this;
    }

    /**
     * Register all actions found in the provided paths.
     */
    public function registerAllPaths(): void
    {
        if ($this->paths->isEmpty()) {
            return;
        }

        foreach ((new Finder)->in($this->paths->toArray())->files() as $file) {
            $this->register(
                $this->getClassnameFromPathname($file->getPathname())
            );
        }
    }

    /**
     * Register one action either through an object or its classname.
     *
     * @param Action|string $action
     * @throws ReflectionException
     */
    public function register($action): void
    {
        if (! $this->isAction($action) || $this->isRegistered($action)) {
            return;
        }

        $action::register();
        $this->registeredActions->push(is_string($action) ? $action : get_class($action));
    }

    /**
     * Determine if an object or its classname is an Action.
     *
     * @param Action|string $action
     * @return bool
     * @throws ReflectionException
     */
    public function isAction($action): bool
    {
        return is_subclass_of($action, Action::class) &&
            ! (new ReflectionClass($action))->isAbstract();
    }

    /**
     * Determine if an action has already been loaded.
     *
     * @param Action|string $action
     * @return bool
     */
    public function isRegistered($action): bool
    {
        $class = is_string($action) ? $action : get_class($action);

        return $this->registeredActions->contains($class);
    }

    /**
     * Returns a collection of all actions that have been registered.
     *
     * @return Collection
     */
    public function getRegisteredActions(): Collection
    {
        return $this->registeredActions;
    }

    /**
     * Get the fully-qualified name of a class from its pathname.
     *
     * @param string $pathname
     * @return string
     */
    protected function getClassnameFromPathname(string $pathname): string
    {
        return App::getNamespace() . str_replace(
            ['/', '.php'],
            ['\\', ''],
            Str::after($pathname, realpath(app_path()).DIRECTORY_SEPARATOR)
        );
    }
}
