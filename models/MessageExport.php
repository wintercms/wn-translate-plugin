<?php

namespace Winter\Translate\Models;

use Backend\Models\ExportModel;

class MessageExport extends ExportModel
{
    /*
     * @deprecated since version 2.3.2, use \Winter\Translate\Models\Message::CODE_COLUMN_NAME directly
     * @see \Winter\Translate\Models\Message::CODE_COLUMN_NAME
     */
    const CODE_COLUMN_NAME = \Winter\Translate\Models\Message::CODE_COLUMN_NAME;

    /*
     * @deprecated since version 2.3.2, use \Winter\Translate\Models\Message::DEFAULT_COLUMN_NAME directly
     * @see \Winter\Translate\Models\Message::DEFAULT_COLUMN_NAME
     */
    const DEFAULT_COLUMN_NAME = \Winter\Translate\Models\Message::DEFAULT_COLUMN_NAME;

    /**
     * Exports the message data with each locale in a separate column.
     *
     * code      | default   | en    | de    | fr    | found
     * --------------------------------------------------
     * title     | Title     | Title | Titel | Titre | 1
     * name      | Name      | Name  | Name  | Prénom| 0
     * ...
     */
    public function exportData(array $columns, ?string $sessionKey = null): array
    {
        return Message::all()->map(function ($message) use ($columns) {
            $data = $message->message_data;

            $result = [];
            foreach ($columns as $column) {
                $result[$column] = array_key_exists($column, $data)
                    ? $data[$column]
                    : ($message->$column ?? '');
            }
            return $result;
        })->toArray();
    }

    /**
     * Returns columns for export (import columns + 'found' flag)
     */
    public static function getColumns(): array
    {
        return Message::getColumns() + ['found' => 'found'];
    }
}
