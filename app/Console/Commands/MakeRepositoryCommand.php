<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeRepositoryCommand extends GeneratorCommand
{
    protected $name = 'make:repository';
    protected $description = 'Create a new repository class for a model, with an optional controller and requests';
    protected $type = 'Repository';

    private $className;
    private $model;

    public function handle()
    {
        if ($this->option('all')) {
            $this->input->setOption('controller', true);
            $this->input->setOption('dto', true);
            $this->input->setOption('model', true);
        }

        $this->setClassName();

        $path = $this->getPath($this->className);

        if ($this->alreadyExists($this->getNameInput())) {
            $this->error($this->type.' already exists!');
            return false;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($this->className));

        $this->info($this->type.' created successfully.');
        $this->line("<info>Created $this->type :</info> $this->className");

        if ($this->option('model')) {
            $this->createModelWithMigration();
        }

        if ($this->option('dto')) {
            $dtoName = $this->model . 'Data';
            $this->call('make:dto', ['name' => $dtoName]);
        }

        if ($this->option('controller')) {
            $this->createControllerAndRequestsAndResource();
        }
    }

    protected function parseName($name)
    {
        $rootNamespace = $this->laravel->getNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        if (str_contains($name, '/')) {
            $name = str_replace('/', '\\', $name);
        }

        return $this->parseName($this->getDefaultNamespace(trim($rootNamespace, '\\')).'\\'.$name);
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return base_path().'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function getStub()
    {
        return $this->option('dto')
            ? __DIR__ . '/../../../stubs/repository-with-dto.stub'
            : __DIR__ . '/../../../stubs/repository.stub';
    }

    private function setClassName()
    {
        $name = ucfirst(Str::camel($this->argument('name')));

        $this->name = $name;
        $this->model = $this->argument('model');

        $this->className = $this->parseName($name);
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\App\Http\Repositories';
    }

    protected function replaceClass($stub, $name)
    {
        if (!$this->argument('name')) {
            throw new \InvalidArgumentException("Missing required argument: model name");
        }

        $stub = parent::replaceClass($stub, $name);

        $stub = str_replace('{{ class }}', $this->argument('name'), $stub);
        $stub = str_replace('{{ model }}', $this->model, $stub);

        return $stub;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the repository class'],
            ['model', InputArgument::REQUIRED, 'The name of the model'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Create all related classes (controller, requests, DTO, and resource)'],
            ['dto', 'd', InputOption::VALUE_NONE, 'Create a DTO for the repository'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Create a controller and requests for the repository'],
            ['model', 'm', InputOption::VALUE_NONE, 'Create a model with a migration'],
        ];
    }

    /**
     * Create the controller and request classes.
     */
    private function createControllerAndRequestsAndResource()
    {
        $controllerName = $this->model . 'Controller';
        $this->call('make:controller', [
            'name' => "App\Http\Controllers\\{$controllerName}",
            '--model' => $this->model,
            '--requests' => true,
        ]);

        $this->info("Controller {$controllerName} and associated requests created successfully.");

        $this->createResource();
    }

    /**
     * Create the resource.
     */
    private function createResource()
    {
        $resourceName = $this->model . 'Resource';
        $this->call('make:resource', [
            'name' => "App\Http\Resources\\{$resourceName}",
        ]);

        $this->info("Resource {$resourceName} created successfully.");
    }

    /**
     * Create the model with a migration.
     */
    private function createModelWithMigration()
    {
        $this->call('make:model', [
            'name' => "App\Models\\{$this->model}",
            '--migration' => true,
        ]);

        $this->info("Model {$this->model} with migration created successfully.");
    }
}
