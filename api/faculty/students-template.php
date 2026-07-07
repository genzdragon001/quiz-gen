<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$faculty = requireFaculty();

// Download CSV template with header + sample rows
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="student_template.csv"');

$output = fopen('php://output', 'w');
// Header row
fputcsv($output, ['student_id', 'name', 'email', 'year_section']);
// Sample rows
fputcsv($output, ['2021001', 'Juan Dela Cruz', 'juan@example.com', 'BSCPE 3-1']);
fputcsv($output, ['2021002', 'Maria Santos', 'maria@example.com', 'BSCPE 3-1']);
fputcsv($output, ['2021003', 'Pedro Reyes', '', 'BSCPE 3-2']);
fclose($output);