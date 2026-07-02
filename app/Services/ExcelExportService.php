<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Upload;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExcelExportService
{

    public function transactionExport($transactions, $fileName)
    {
        $data = [];

        foreach ($transactions as $t) {
            $data[] = [
                'ID' => $t->id,
                'User Name' => $t->user?->name ?? '-',
                'User Email' => $t->user?->email ?? '-',
                'Order Reference' => $t->order?->reference ?? '-',
                'Amount' => number_format((float) $t->charged_price, 3),
                'Currency' => $t->currency,
                'Status' => $t->status,
                'Origin' => $t->origin ?? '-',
                'Created At' => $t->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        $this->exportToCSV($data, $fileName);
    }


    public function orderExport($orders, $fileName)
    {
        $data = [];
        foreach ($orders as $order) {
            $data[] = $this->orderExportReady($order);
        }
        $this->exportToCSV($data, $fileName);
    }

    public function orderExportReady($order)
    {
        return [
            'Order ID' =>  $order->id,
            'Reference' =>  $order->reference,
            'User' =>  $order->user ? $order->user->name : '-',
            'Carrier' =>  $order->carrier ? $order->carrier->name : '-',
            'City' =>  $order->city ? $order->city->name : '-',
            'Area' =>  $order->area ? $order->area->name : '-',
            'Order Type' =>  $order->order_type ? $order->order_type : '-',
            'Status' =>  $order->status ? \App\Models\Order::GET_STATUS()[$order->status] : '-',
            'Total Quantity' =>  $order->total_qty ?? 0,
            'Success Quantity' =>  $order->success_qty ?? 0,
            'Reject Quantity' =>  $order->reject_qty ?? 0,
            'Total' =>  $order->total ?? 0,
            'Subtotal' =>  $order->subtotal ?? 0,
            'Currency' =>  $order->currency ?? 'USD',
            'Created At' =>  $order->created_at ? $order->created_at->format('d/m/Y, H:i') : '-',
            'Notes' =>  $order->notes ?? '-',
        ];
    }

    public function exportToCSV($data, $fileName, $headers = null)
    {
        $fileExist = Storage::disk('public')->exists($fileName);
        $fh = fopen(storage_path('app/public/' . $fileName), 'a');
        fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM

        foreach ($data as $index => $line) {
            // Ensure $line is an array
            if (!is_array($line)) {
                // Convert non-array $line to an array (e.g., if it's a string or object)
                $line = (array) $line;
            }

            // Flatten the array to handle nested arrays or objects
            $flattenedLine = array_map(function ($value) {
                if (is_array($value) || is_object($value)) {
                    // Convert nested arrays or objects to a string representation
                    return json_encode($value);
                }
                return $value; // Return scalar values as-is
            }, $line);

            // Write headers if file doesn't exist and it's the first row
            if ($index == 0 && !$fileExist) {
                if ($headers && is_array($headers)) {
                    // Flatten headers to handle any nested arrays or objects
                    $flattenedHeaders = array_map(function ($value) {
                        if (is_array($value) || is_object($value)) {
                            return json_encode($value);
                        }
                        return $value;
                    }, $headers);
                    fputcsv($fh, $flattenedHeaders);
                } else {
                    fputcsv($fh, array_keys($line));
                }
            }

            // Write the flattened row
            fputcsv($fh, array_values($flattenedLine));
        }

        fclose($fh);
    }
}
