<?php
/**
 * Argora Foundry
 *
 * A modular PHP boilerplate for building SaaS applications, admin panels, and control systems.
 *
 * @package    App
 * @author     Taras Kondratyuk <help@argora.org>
 * @copyright  Copyright (c) 2025 Argora
 * @license    MIT License
 * @link       https://github.com/getargora/foundry
 */

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SparkController extends Controller
{
    public function listOrders(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $db = $this->container->get('db');

        // Map fields to fully qualified columns for filtering/sorting
        // Adjust field names if needed
        $allowedFieldsMap = [
            'user_id' => 'o.id',
            'user_id' => 'o.user_id',
            'service_type' => 'o.service_type',
            'status' => 'o.status',
            'amount_due' => 'o.amount_due',
            'currency' => 'o.currency',
            'created_at' => 'o.created_at',
            'paid_at' => 'o.paid_at',
            'username' => 'u.username'
        ];

        // --- SORTING ---
        $sortField = 'o.created_at'; // default sort by date
        $sortDir = 'desc';
        if (!empty($params['order'])) {
            $orderParts = explode(',', $params['order']);
            if (count($orderParts) === 2) {
                $fieldCandidate = preg_replace('/[^a-zA-Z0-9_]/', '', $orderParts[0]);
                if (array_key_exists($fieldCandidate, $allowedFieldsMap)) {
                    $sortField = $allowedFieldsMap[$fieldCandidate];
                }
                $sortDir = strtolower($orderParts[1]) === 'asc' ? 'asc' : 'desc';
            }
        }

        // --- PAGINATION ---
        $page = 1;
        $size = 10;
        if (!empty($params['page'])) {
            $pageParts = explode(',', $params['page']);
            if (count($pageParts) === 2) {
                $pageNum = (int)$pageParts[0];
                $pageSize = (int)$pageParts[1];
                if ($pageNum > 0) {
                    $page = $pageNum;
                }
                if ($pageSize > 0) {
                    $size = $pageSize;
                }
            }
        }
        $offset = ($page - 1) * $size;

        // --- FILTERING ---
        $whereClauses = [];
        $bindParams = [];
        foreach ($params as $key => $value) {
            if (preg_match('/^filter\d+$/', $key)) {
                $fParts = explode(',', $value);
                if (count($fParts) === 3) {
                    list($fField, $fOp, $fVal) = $fParts;
                    $fField = preg_replace('/[^a-zA-Z0-9_]/', '', $fField);

                    // Ensure the field is allowed and fully qualify it
                    if (!array_key_exists($fField, $allowedFieldsMap)) {
                        // Skip unknown fields
                        continue;
                    }
                    $column = $allowedFieldsMap[$fField];

                    switch ($fOp) {
                        case 'eq':
                            $whereClauses[] = "$column = :f_{$key}";
                            $bindParams["f_{$key}"] = $fVal;
                            break;
                        case 'cs':
                            $whereClauses[] = "$column LIKE :f_{$key}";
                            $bindParams["f_{$key}"] = "%$fVal%";
                            break;
                        case 'sw':
                            $whereClauses[] = "$column LIKE :f_{$key}";
                            $bindParams["f_{$key}"] = "$fVal%";
                            break;
                        case 'ew':
                            $whereClauses[] = "$column LIKE :f_{$key}";
                            $bindParams["f_{$key}"] = "%$fVal";
                            break;
                        // Add other cases if needed
                    }
                }
            }
        }

        // Check admin status and apply user filter if needed
        $userCondition = '';
        if ($_SESSION['auth_roles'] !== 0) { // not admin
            $userId = $_SESSION['auth_user_id'];
            $userCondition = "o.user_id = :userId";
            $bindParams["userId"] = $userId;
        }

        // Base SQL
        $sqlBase = "
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
        ";

        // Combine user condition and search filters
        if (!empty($whereClauses)) {
            // We have search conditions
            $filtersCombined = "(" . implode(" OR ", $whereClauses) . ")";
            if ($userCondition) {
                // If userCondition exists and we have filters
                // we do userCondition AND (filters OR...)
                $sqlWhere = "WHERE $userCondition AND $filtersCombined";
            } else {
                // No user restriction, just the filters
                $sqlWhere = "WHERE $filtersCombined";
            }
        } else {
            // No search filters
            if ($userCondition) {
                // Only user condition
                $sqlWhere = "WHERE $userCondition";
            } else {
                // No filters, no user condition
                $sqlWhere = '';
            }
        }

        // Count total results
        $totalSql = "SELECT COUNT(DISTINCT o.id) AS total $sqlBase $sqlWhere";
        $totalCount = $db->selectValue($totalSql, $bindParams);

        // Data query
        $selectFields = "
            o.id,
            o.user_id,
            o.service_type,
            o.status,
            o.amount_due,
            o.currency,
            o.created_at,
            o.paid_at,
            u.username
        ";

        $dataSql = "
            SELECT $selectFields
            $sqlBase
            $sqlWhere
            ORDER BY $sortField $sortDir
            LIMIT $offset, $size
        ";

        $records = $db->select($dataSql, $bindParams);

        // Ensure records is always an array
        if (!$records) {
            $records = [];
        }

        $payload = [
            'records' => $records,
            'results' => $totalCount
        ];

        $response = $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function listTransactions(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $db = $this->container->get('db');

        // Map fields to fully qualified columns for filtering/sorting
        $allowedFieldsMap = [
            'user_id' => 'tr.user_id',
            'type' => 'tr.type',
            'category' => 'tr.category',
            'description' => 'tr.description',
            'amount' => 'tr.amount',
            'currency' => 'tr.currency',
            'status' => 'tr.status',
            'created_at' => 'tr.created_at',
            'username' => 'u.username'
        ];

        // --- SORTING ---
        $sortField = 'tr.created_at'; // default sort by date
        $sortDir = 'desc';
        if (!empty($params['order'])) {
            $orderParts = explode(',', $params['order']);
            if (count($orderParts) === 2) {
                $fieldCandidate = preg_replace('/[^a-zA-Z0-9_]/', '', $orderParts[0]);
                if (array_key_exists($fieldCandidate, $allowedFieldsMap)) {
                    $sortField = $allowedFieldsMap[$fieldCandidate];
                }
                $sortDir = strtolower($orderParts[1]) === 'asc' ? 'asc' : 'desc';
            }
        }

        // --- PAGINATION ---
        $page = 1;
        $size = 10;
        if (!empty($params['page'])) {
            $pageParts = explode(',', $params['page']);
            if (count($pageParts) === 2) {
                $pageNum = (int)$pageParts[0];
                $pageSize = (int)$pageParts[1];
                if ($pageNum > 0) {
                    $page = $pageNum;
                }
                if ($pageSize > 0) {
                    $size = $pageSize;
                }
            }
        }
        $offset = ($page - 1) * $size;

        // --- FILTERING ---
        $whereClauses = [];
        $bindParams = [];
        foreach ($params as $key => $value) {
            if (preg_match('/^filter\d+$/', $key)) {
                $fParts = explode(',', $value);
                if (count($fParts) === 3) {
                    list($fField, $fOp, $fVal) = $fParts;
                    $fField = preg_replace('/[^a-zA-Z0-9_]/', '', $fField);

                    // Ensure the field is allowed and fully qualify it
                    if (!array_key_exists($fField, $allowedFieldsMap)) {
                        // Skip unknown fields
                        continue;
                    }
                    $column = $allowedFieldsMap[$fField];

                    switch ($fOp) {
                        case 'eq':
                            $whereClauses[] = "$column = :f_{$key}";
                            $bindParams["f_{$key}"] = $fVal;
                            break;
                        case 'cs':
                            $whereClauses[] = "$column LIKE :f_{$key}";
                            $bindParams["f_{$key}"] = "%$fVal%";
                            break;
                        case 'sw':
                            $whereClauses[] = "$column LIKE :f_{$key}";
                            $bindParams["f_{$key}"] = "$fVal%";
                            break;
                        case 'ew':
                            $whereClauses[] = "$column LIKE :f_{$key}";
                            $bindParams["f_{$key}"] = "%$fVal";
                            break;
                        // Add other cases if needed
                    }
                }
            }
        }

        // Check admin status and apply user filter if needed
        $userCondition = '';
        if ($_SESSION['auth_roles'] !== 0) { // not admin
            $userId = $_SESSION['auth_user_id'];
            $userCondition = "tr.user_id = :userId";
            $bindParams["userId"] = $userId;
        }

        // Base SQL
        $sqlBase = "
            FROM transactions tr
            LEFT JOIN users u ON tr.user_id = u.id
        ";

        // Combine user condition and search filters
        if (!empty($whereClauses)) {
            // We have search conditions
            $filtersCombined = "(" . implode(" OR ", $whereClauses) . ")";
            if ($userCondition) {
                // If userCondition exists and we have filters
                // we do userCondition AND (filters OR...)
                $sqlWhere = "WHERE $userCondition AND $filtersCombined";
            } else {
                // No user restriction, just the filters
                $sqlWhere = "WHERE $filtersCombined";
            }
        } else {
            // No search filters
            if ($userCondition) {
                // Only user condition
                $sqlWhere = "WHERE $userCondition";
            } else {
                // No filters, no user condition
                $sqlWhere = '';
            }
        }

        // Count total results
        $totalSql = "SELECT COUNT(DISTINCT tr.id) AS total $sqlBase $sqlWhere";
        $totalCount = $db->selectValue($totalSql, $bindParams);

        // Data query
        $selectFields = "
            tr.user_id,
            tr.type,
            tr.category,
            tr.description,
            tr.amount,
            tr.currency,
            tr.status,
            tr.created_at,
            u.username
        ";

        $dataSql = "
            SELECT $selectFields
            $sqlBase
            $sqlWhere
            ORDER BY $sortField $sortDir
            LIMIT $offset, $size
        ";

        $records = $db->select($dataSql, $bindParams);

        // Ensure records is always an array
        if (!$records) {
            $records = [];
        }

        $payload = [
            'records' => $records,
            'results' => $totalCount
        ];

        $response = $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response;
    }

}