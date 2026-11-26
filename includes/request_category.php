<?php

declare(strict_types=1);

function requestCategoryFromRow(array $row): string
{
    $equipmentCategory = trim((string) ($row['equipment_category'] ?? ''));
    if ($equipmentCategory !== '') {
        return $equipmentCategory;
    }

    return trim((string) ($row['category'] ?? ''));
}

function requestCategoryIsElectrical(string $category): bool
{
    return strtolower(preg_replace('/[^a-z]/i', '', trim($category))) === 'electrical';
}
