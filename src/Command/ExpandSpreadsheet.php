<?php

namespace App\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use OpenAI\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
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
    public function __construct(private readonly Client $openai)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $form = $this->form();
        if (empty($form)) {
            return Command::INVALID;
        }

        $schema = [];
        foreach ($form['outputs'] as $definition) {
            $schema[$definition['column']] = [
                'type' => 'string',
                'description' => $definition['description'],
            ];
        }

        $prompt = <<<'PROMPT'
You are a command-line tool to help expanding existing spreadsheet data with additional information based on data and the prompt.
Expected input is rows from a spreadsheet, formatted as a JSON array.
Based on the "save_output" tool schema, infer and expand the data with more information.
PROMPT;

        $reader = IOFactory::createReaderForFile($form['source']);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($form['source']);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray();
        $headers = array_shift($rows); // remove heading row

        $result = $this->openai->chat()
            ->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => trim($prompt)],
                    [
                        'role' => 'system',
                        'content' => 'The input data structure can be described as '.json_encode($form['inputs']),
                    ],
                    ['role' => 'user', 'content' => 'Following is the input from the CSV file.'],
                    [
                        'role' => 'user',
                        'content' => json_encode($rows),
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'save_output',
                            'description' => 'Save or update the LLM output to destination file.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'rows' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => $schema,
                                            'required' => array_keys($schema),
                                        ],
                                    ],
                                ],
                                'required' => ['rows'],
                            ],
                        ],
                    ],
                ],
                'tool_choice' => [
                    'type' => 'function',
                    'function' => [
                        'name' => 'save_output',
                    ],
                ],
            ]);

        $message = $result->choices[0]->message;
        $arguments = $message->toolCalls[0]->function->arguments;
        $args = json_decode($arguments, true);

        $headers = array_merge($headers, array_keys($schema));
        foreach ($rows as $i => $row) {
            $rows[$i] = array_merge($row, array_values($args['rows'][$i]));
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers);
        $sheet->fromArray($rows, null, 'A2');
        $writer = new Csv($spreadsheet);

        $writer->save($form['destination']);

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

        $reader = IOFactory::createReaderForFile($source);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 1; // read only first row
            }
        });
        $spreadsheet = $reader->load($source);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $projection = multiselect(
            'Input columns',
            options: $rows[0],
            required: true,
            hint: 'Columns to use for building context.',
        );

        $inputs = [];

        foreach ($projection as $column) {
            $description = textarea(
                "$column's column description",
                required: true,
                hint: "Describe existing content in $column column.",
            );

            $inputs[] = compact('column', 'description');
        }

        $outputs = [];
        while (true) {
            if (! empty($outputs)) {
                $confirmed = confirm('Do you want to keep adding more target columns?');
                if (! $confirmed) {
                    break;
                }
            }

            $column = text(
                'Output column name',
                required: true,
            );
            $description = textarea(
                'Output column description',
                required: true,
                hint: "Describe output content in $column column.",
            );

            $outputs[] = compact('column', 'description');
        }

        $prompt = textarea(
            'Extra prompt',
            hint: 'Prompt text to appended to LLM input.',
        );

        $destination = text(
            'Destination file',
            placeholder: 'examples/output.csv',
            required: true,
            hint: 'Full or relative path to the output file (must be a CSV file).',
        );

        $confirmed = confirm('Confirm and proceed?');
        if (! $confirmed) {
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
