#!/usr/bin/env php
<?php
/**
 * Generate Metrics - Static Dashboard Builder
 *
 * Parses PHPUnit coverage XML and PHPCS output to generate static dashboard files:
 * - dashboard/metrics.json (machine-readable)
 * - dashboard/metrics.md (human-readable)
 *
 * Usage:
 *   php bin/generate-metrics.php
 *   composer metrics:generate
 */

$rootDir = dirname(__DIR__);
$dashboardDir = $rootDir . '/dashboard';
$coverageFile = $dashboardDir . '/coverage/clover.xml';

// Ensure dashboard directory exists
if (!is_dir($dashboardDir)) {
    mkdir($dashboardDir, 0755, true);
}

echo "Generating project metrics...\n";

// Parse coverage data
$coverage = parseCoverageXML($coverageFile);

// Parse quality data (PHPCS)
$quality = parseCodeQuality($rootDir);

// Get test stats
$testStats = getTestStats($rootDir);

// Build metrics object
$metrics = [
    'generated' => date('c'),
    'git_commit' => getGitCommit(),
    'coverage' => $coverage,
    'quality' => $quality,
    'tests' => $testStats,
];

// Write JSON
$jsonFile = $dashboardDir . '/metrics.json';
file_put_contents($jsonFile, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "✓ Generated: dashboard/metrics.json\n";

// Write Markdown
$mdFile = $dashboardDir . '/metrics.md';
file_put_contents($mdFile, generateMarkdownReport($metrics));
echo "✓ Generated: dashboard/metrics.md\n";

echo "\n✅ Dashboard build complete\n";
echo "   View: open dashboard/coverage/index.html\n";
echo "   Read: cat dashboard/metrics.md\n";

// ============================================================================
// FUNCTIONS
// ============================================================================

/**
 * Parse PHPUnit Clover XML coverage report
 */
function parseCoverageXML($file) {
    if (!file_exists($file)) {
        return [
            'status' => 'error',
            'message' => 'Coverage report not found. Run: composer test:coverage',
            'line_percentage' => 0,
            'function_percentage' => 0,
            'class_percentage' => 0,
        ];
    }

    $xml = simplexml_load_file($file);
    if (!$xml) {
        return [
            'status' => 'error',
            'message' => 'Failed to parse coverage XML',
            'line_percentage' => 0,
        ];
    }

    // Find project metrics
    $projectMetrics = $xml->project->metrics;

    $totalLines = (int)$projectMetrics['statements'];
    $coveredLines = (int)$projectMetrics['coveredstatements'];
    $totalMethods = (int)$projectMetrics['methods'];
    $coveredMethods = (int)$projectMetrics['coveredmethods'];
    $totalClasses = (int)$projectMetrics['classes'];
    $coveredClasses = (int)$projectMetrics['coveredclasses'];

    $linePct = $totalLines > 0 ? round(($coveredLines / $totalLines) * 100, 2) : 0;
    $methodPct = $totalMethods > 0 ? round(($coveredMethods / $totalMethods) * 100, 2) : 0;
    $classPct = $totalClasses > 0 ? round(($coveredClasses / $totalClasses) * 100, 2) : 0;

    // Parse per-file coverage
    $files = [];
    foreach ($xml->xpath('//file') as $file) {
        $filename = (string)$file['name'];

        // Only include plugin files
        if (strpos($filename, 'inat-observations-wp') === false) {
            continue;
        }

        $fileMetrics = $file->metrics;
        $fileLines = (int)$fileMetrics['statements'];
        $fileCovered = (int)$fileMetrics['coveredstatements'];

        if ($fileLines > 0) {
            $filePct = round(($fileCovered / $fileLines) * 100, 2);

            // Convert absolute path to relative path (remove workspace prefix)
            $relativePath = $filename;
            $projectRoot = dirname(__DIR__);
            if (strpos($filename, $projectRoot) === 0) {
                $relativePath = substr($filename, strlen($projectRoot) + 1);
            }

            $files[] = [
                'file' => basename($filename),
                'path' => $relativePath,
                'line_coverage' => $filePct,
                'total_lines' => $fileLines,
                'covered_lines' => $fileCovered,
                'status' => getStatusForCoverage($filePct),
            ];
        }
    }

    // Sort files by coverage (lowest first)
    usort($files, function($a, $b) {
        return $a['line_coverage'] <=> $b['line_coverage'];
    });

    return [
        'status' => 'success',
        'line_percentage' => $linePct,
        'function_percentage' => $methodPct,
        'class_percentage' => $classPct,
        'total_lines' => $totalLines,
        'covered_lines' => $coveredLines,
        'total_methods' => $totalMethods,
        'covered_methods' => $coveredMethods,
        'total_classes' => $totalClasses,
        'covered_classes' => $coveredClasses,
        'files' => $files,
    ];
}

/**
 * Parse code quality metrics (PHPCS)
 */
function parseCodeQuality($rootDir) {
    // Run PHPCS and capture output
    $phpcsCmd = "cd $rootDir && composer lint --no-interaction 2>&1";
    $output = shell_exec($phpcsCmd);

    // Parse PHPCS output for warnings/errors
    $warnings = 0;
    $errors = 0;

    if (preg_match('/(\d+) ERRORS?, (\d+) WARNINGS?/', $output, $matches)) {
        $errors = (int)$matches[1];
        $warnings = (int)$matches[2];
    }

    return [
        'warnings' => $warnings,
        'errors' => $errors,
        'standard' => 'WordPress',
        'status' => ($errors === 0 && $warnings === 0) ? 'pass' : 'fail',
    ];
}

/**
 * Get test statistics from PHPUnit
 */
function getTestStats($rootDir) {
    // Try to parse from previous test run
    // For now, return placeholder (would need PHPUnit JSON logger)

    return [
        'total' => 0, // TODO: Parse from PHPUnit output
        'passed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'execution_time_seconds' => 0.0,
    ];
}

/**
 * Get current git commit hash
 */
function getGitCommit() {
    $commit = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null'));
    return $commit ?: 'unknown';
}

/**
 * Determine status level for coverage percentage
 */
function getStatusForCoverage($pct) {
    if ($pct >= 97) return 'excellent';
    if ($pct >= 80) return 'good';
    if ($pct >= 60) return 'warning';
    return 'critical';
}

/**
 * Generate Markdown report
 */
function generateMarkdownReport($metrics) {
    $md = "# iNaturalist Observations WP - Project Metrics\n\n";
    $md .= "**Generated:** {$metrics['generated']}\n";
    $md .= "**Git Commit:** {$metrics['git_commit']}\n\n";
    $md .= "---\n\n";

    // Overall Health
    $healthScore = calculateOverallHealth($metrics);
    $healthIcon = getHealthIcon($healthScore);

    $md .= "## Overall Project Health\n\n";
    $md .= "{$healthIcon} **{$healthScore}/100** - " . getHealthLabel($healthScore) . "\n\n";

    // Code Coverage
    $md .= "## Code Coverage\n\n";

    if ($metrics['coverage']['status'] === 'success') {
        $lineCov = $metrics['coverage']['line_percentage'];
        $funcCov = $metrics['coverage']['function_percentage'];
        $classCov = $metrics['coverage']['class_percentage'];

        $md .= "| Metric | Coverage | Status |\n";
        $md .= "|--------|----------|--------|\n";
        $md .= "| **Line Coverage** | {$lineCov}% | " . getStatusBadge($lineCov) . " |\n";
        $md .= "| **Function Coverage** | {$funcCov}% | " . getStatusBadge($funcCov) . " |\n";
        $md .= "| **Class Coverage** | {$classCov}% | " . getStatusBadge($classCov) . " |\n\n";

        $md .= "**Summary:**\n";
        $md .= "- Total Lines: {$metrics['coverage']['covered_lines']}/{$metrics['coverage']['total_lines']}\n";
        $md .= "- Total Methods: {$metrics['coverage']['covered_methods']}/{$metrics['coverage']['total_methods']}\n";
        $md .= "- Total Classes: {$metrics['coverage']['covered_classes']}/{$metrics['coverage']['total_classes']}\n\n";

        // Per-file coverage
        if (!empty($metrics['coverage']['files'])) {
            $md .= "### Coverage by File\n\n";
            $md .= "Files sorted by coverage (lowest first - needs attention):\n\n";
            $md .= "| File | Coverage | Lines | Status |\n";
            $md .= "|------|----------|-------|--------|\n";

            foreach ($metrics['coverage']['files'] as $file) {
                $icon = getStatusIcon($file['status']);
                $md .= "| {$file['file']} | {$file['line_coverage']}% | {$file['covered_lines']}/{$file['total_lines']} | {$icon} |\n";
            }
            $md .= "\n";
        }

        $md .= "**Target:** 97%+ line coverage\n\n";
    } else {
        $md .= "⚠️ Coverage data not available. Run `composer test:coverage` first.\n\n";
    }

    // Code Quality
    $md .= "## Code Quality\n\n";

    $qualityIcon = $metrics['quality']['status'] === 'pass' ? '✅' : '❌';
    $md .= "{$qualityIcon} **Standard:** {$metrics['quality']['standard']}\n\n";
    $md .= "| Metric | Count | Target |\n";
    $md .= "|--------|-------|--------|\n";
    $md .= "| Errors | {$metrics['quality']['errors']} | 0 |\n";
    $md .= "| Warnings | {$metrics['quality']['warnings']} | 0 |\n\n";

    if ($metrics['quality']['errors'] > 0 || $metrics['quality']['warnings'] > 0) {
        $md .= "⚠️ **Action Required:** Run `composer lint:fix` to auto-fix issues, then manually address remaining problems.\n\n";
    }

    // Test Suite
    $md .= "## Test Suite\n\n";
    $md .= "| Metric | Value |\n";
    $md .= "|--------|-------|\n";
    $md .= "| Total Tests | {$metrics['tests']['total']} |\n";
    $md .= "| Passed | {$metrics['tests']['passed']} |\n";
    $md .= "| Failed | {$metrics['tests']['failed']} |\n";
    $md .= "| Skipped | {$metrics['tests']['skipped']} |\n\n";

    // Quality Gates
    $md .= "## Quality Gates\n\n";

    $gates = [
        ['Coverage ≥ 97%', $metrics['coverage']['line_percentage'] >= 97],
        ['Warnings = 0', $metrics['quality']['warnings'] === 0],
        ['Errors = 0', $metrics['quality']['errors'] === 0],
    ];

    foreach ($gates as $gate) {
        $icon = $gate[1] ? '✅' : '❌';
        $md .= "- {$icon} {$gate[0]}\n";
    }

    $md .= "\n---\n\n";
    $md .= "*Dashboard auto-generated by `composer dashboard:build`*\n";
    $md .= "*View detailed HTML coverage: `open dashboard/coverage/index.html`*\n";

    return $md;
}

/**
 * Calculate overall health score
 */
function calculateOverallHealth($metrics) {
    $scores = [];

    if ($metrics['coverage']['status'] === 'success') {
        $scores[] = $metrics['coverage']['line_percentage'];
    }

    // Quality score (100 if no issues, 0 otherwise)
    $qualityScore = ($metrics['quality']['errors'] === 0 && $metrics['quality']['warnings'] === 0) ? 100 : 0;
    $scores[] = $qualityScore;

    return !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
}

/**
 * Get health icon
 */
function getHealthIcon($score) {
    if ($score >= 97) return '🟢';
    if ($score >= 80) return '🟡';
    return '🔴';
}

/**
 * Get health label
 */
function getHealthLabel($score) {
    if ($score >= 97) return 'Excellent';
    if ($score >= 80) return 'Good';
    if ($score >= 60) return 'Needs Improvement';
    return 'Critical';
}

/**
 * Get status badge
 */
function getStatusBadge($pct) {
    if ($pct >= 97) return '🟢 Excellent';
    if ($pct >= 80) return '🟢 Good';
    if ($pct >= 60) return '🟡 Warning';
    return '🔴 Critical';
}

/**
 * Get status icon
 */
function getStatusIcon($status) {
    $icons = [
        'excellent' => '🟢',
        'good' => '🟢',
        'warning' => '🟡',
        'critical' => '🔴',
    ];
    return $icons[$status] ?? '⚪';
}
