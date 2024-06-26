<?php

namespace App\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:expand-spreadsheet',
    description: 'Expand a spreadsheet with additional information based on data and the prompt.',
    aliases: ['expand']
)]
class ExpandSpreadsheet extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $form = $this->form();
        if (empty($form)) {
            return Command::INVALID;
        }

        return Command::SUCCESS;
    }

    private function form(): array
    {
        $source = text(
            'Source file',
            placeholder: 'examples/input.csv',
            required: true,
            validate: fn (string $value) => match (true) {
                ! is_file($value) => 'The path must be a valid file.',
                ! is_readable($value) => 'The path must be readable.',
                default => null
            },
            hint: 'Full or relative path to the source file.',
        );
        $projection = ['Name', 'Email address']; // TODO
        $projection = multiselect(
            'Input columns',
            options: $projection,
            required: true,
            hint: 'Columns to use for building context.',
        );

        $inputs = [];

        foreach ($projection as $name) {
            $description = textarea(
                "$name's column description",
                required: true,
                hint: "Describe existing content in $name column.",
            );

            $inputs[] = compact('name', 'description');
        }

        $updates = (int) text(
            'Target columns',
            required: true,
            validate: fn (string $value) => match (true) {
                ! is_numeric($value) => 'The value must be a number.',
                $value < 1 || $value > 10 => 'The value must be between 1 and 10.',
                default => null
            },
            hint: 'No. of columns to to infer.',
        );

        $outputs = [];

        for ($i = 1; $i <= $updates; $i++) {
            $name = text(
                "Output column no. $i's name",
                required: true,
            );
            $description = textarea(
                "$name's column description",
                required: true,
                hint: "Describe output content in $name column.",
            );
            $outputs[] = compact('name', 'description');
        }

        $prompt = textarea(
            'Extra prompt',
            hint: 'Prompt text to appended to LLM input.',
        );

        $destination = text(
            'Destination file',
            placeholder: 'examples/output.csv',
            required: true,
            validate: fn (string $value) => match (true) {
                ! is_file($value) => 'The path must be a valid file.',
                ! is_readable($value) => 'The path must be readable.',
                default => null
            },
            hint: 'Full or relative path to the output file.',
        );

        $confirmed = confirm('Confirm and proceed?');
        if ($confirmed) {
            return [];
        }

        return compact(
            'source',
            'inputs',
            'outputs',
            'prompt',
            'destination',
        );
    }
}
