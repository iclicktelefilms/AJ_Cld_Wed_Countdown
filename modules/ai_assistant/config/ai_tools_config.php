<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI Tool Definitions
 *
 * Each tool maps to a method in Crm_action_service or Crm_reader_service.
 * Schema follows Gemini function calling spec (OpenAPI-compatible).
 *
 * Fields:
 *   name         - Unique tool identifier (snake_case)
 *   description  - Human+AI readable description
 *   service      - Which service class handles this tool
 *   method       - Method name on the service
 *   permission   - Required permission level: read|write|delete|report
 *   confirm      - Whether to ask user confirmation before executing
 *   parameters   - JSON Schema for inputs
 */

return [

    // ── READ TOOLS ──────────────────────────────────────────────────────────

    [
        'name'        => 'search_leads',
        'description' => 'Search and filter leads in the CRM. Supports filters by status, source, assigned staff, date range, and keyword.',
        'service'     => 'Crm_reader_service',
        'method'      => 'search_leads',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'keyword'    => ['type' => 'string',  'description' => 'Search term for name, email, or company'],
                'status'     => ['type' => 'string',  'description' => 'Lead status slug'],
                'source'     => ['type' => 'string',  'description' => 'Lead source'],
                'assigned_to'=> ['type' => 'integer', 'description' => 'Staff ID assigned to lead'],
                'date_from'  => ['type' => 'string',  'description' => 'Start date (YYYY-MM-DD)'],
                'date_to'    => ['type' => 'string',  'description' => 'End date (YYYY-MM-DD)'],
                'limit'      => ['type' => 'integer', 'description' => 'Max results (default 20, max 100)'],
            ],
        ],
    ],

    [
        'name'        => 'get_lead_details',
        'description' => 'Get full details of a specific lead by ID.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_lead_details',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['lead_id'],
            'properties' => [
                'lead_id' => ['type' => 'integer', 'description' => 'Lead ID'],
            ],
        ],
    ],

    [
        'name'        => 'search_clients',
        'description' => 'Search customers/clients by name, email, phone, or city.',
        'service'     => 'Crm_reader_service',
        'method'      => 'search_clients',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'keyword' => ['type' => 'string',  'description' => 'Search term'],
                'city'    => ['type' => 'string',  'description' => 'Filter by city'],
                'country' => ['type' => 'string',  'description' => 'Filter by country'],
                'limit'   => ['type' => 'integer', 'description' => 'Max results'],
            ],
        ],
    ],

    [
        'name'        => 'get_client_details',
        'description' => 'Get complete details of a client including contacts, invoices summary, and recent activity.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_client_details',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['client_id'],
            'properties' => [
                'client_id' => ['type' => 'integer', 'description' => 'Client ID'],
            ],
        ],
    ],

    [
        'name'        => 'get_invoice_summary',
        'description' => 'Get invoices with optional filters. Supports status (unpaid, paid, overdue, draft), client, date range, and amount threshold.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_invoice_summary',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'status'        => ['type' => 'string',  'description' => 'Invoice status: unpaid|paid|overdue|draft|cancelled'],
                'client_id'     => ['type' => 'integer', 'description' => 'Filter by client ID'],
                'min_amount'    => ['type' => 'number',  'description' => 'Minimum invoice amount'],
                'max_amount'    => ['type' => 'number',  'description' => 'Maximum invoice amount'],
                'date_from'     => ['type' => 'string',  'description' => 'Start date (YYYY-MM-DD)'],
                'date_to'       => ['type' => 'string',  'description' => 'End date (YYYY-MM-DD)'],
                'limit'         => ['type' => 'integer', 'description' => 'Max results'],
            ],
        ],
    ],

    [
        'name'        => 'generate_revenue_report',
        'description' => 'Generate revenue report for a given period. Returns total revenue, paid invoices, pending amounts, expenses, and net profit.',
        'service'     => 'Crm_reader_service',
        'method'      => 'generate_revenue_report',
        'permission'  => 'report',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'period'    => ['type' => 'string', 'description' => 'Period: today|week|month|quarter|year|custom'],
                'date_from' => ['type' => 'string', 'description' => 'Start date for custom period'],
                'date_to'   => ['type' => 'string', 'description' => 'End date for custom period'],
                'currency'  => ['type' => 'string', 'description' => 'Currency code (default from settings)'],
            ],
        ],
    ],

    [
        'name'        => 'get_pending_tasks',
        'description' => 'Get pending or in-progress tasks. Filter by assignee, project, priority, due date.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_pending_tasks',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'assigned_to' => ['type' => 'integer', 'description' => 'Staff ID'],
                'project_id'  => ['type' => 'integer', 'description' => 'Project ID'],
                'priority'    => ['type' => 'string',  'description' => 'Priority: low|medium|high|urgent'],
                'overdue'     => ['type' => 'boolean', 'description' => 'Only overdue tasks'],
                'limit'       => ['type' => 'integer', 'description' => 'Max results'],
            ],
        ],
    ],

    [
        'name'        => 'search_projects',
        'description' => 'Search projects by name, status, client, or staff member.',
        'service'     => 'Crm_reader_service',
        'method'      => 'search_projects',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'keyword'   => ['type' => 'string',  'description' => 'Project name keyword'],
                'status'    => ['type' => 'string',  'description' => 'Project status'],
                'client_id' => ['type' => 'integer', 'description' => 'Client ID'],
                'member_id' => ['type' => 'integer', 'description' => 'Staff member ID'],
                'limit'     => ['type' => 'integer', 'description' => 'Max results'],
            ],
        ],
    ],

    [
        'name'        => 'get_ticket_summary',
        'description' => 'Get support tickets with filters by status, priority, department, client.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_ticket_summary',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'status'     => ['type' => 'string',  'description' => 'open|closed|answered|in_progress'],
                'priority'   => ['type' => 'string',  'description' => 'low|medium|high|urgent'],
                'client_id'  => ['type' => 'integer', 'description' => 'Client ID'],
                'department' => ['type' => 'integer', 'description' => 'Department ID'],
                'limit'      => ['type' => 'integer', 'description' => 'Max results'],
            ],
        ],
    ],

    [
        'name'        => 'get_staff_list',
        'description' => 'Get list of staff members with their roles and departments.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_staff_list',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'active_only' => ['type' => 'boolean', 'description' => 'Only active staff'],
                'keyword'     => ['type' => 'string',  'description' => 'Search by name'],
            ],
        ],
    ],

    [
        'name'        => 'get_expense_summary',
        'description' => 'Get expense report for a period. Returns total expenses by category.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_expense_summary',
        'permission'  => 'report',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'period'    => ['type' => 'string', 'description' => 'today|week|month|quarter|year|custom'],
                'date_from' => ['type' => 'string', 'description' => 'Start date'],
                'date_to'   => ['type' => 'string', 'description' => 'End date'],
                'category'  => ['type' => 'integer', 'description' => 'Expense category ID'],
            ],
        ],
    ],

    [
        'name'        => 'get_contracts',
        'description' => 'Retrieve contracts with optional filters.',
        'service'     => 'Crm_reader_service',
        'method'      => 'get_contracts',
        'permission'  => 'read',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'client_id'  => ['type' => 'integer', 'description' => 'Client ID'],
                'status'     => ['type' => 'integer', 'description' => 'Status ID'],
                'expiring_soon' => ['type' => 'boolean', 'description' => 'Contracts expiring within 30 days'],
                'limit'      => ['type' => 'integer', 'description' => 'Max results'],
            ],
        ],
    ],

    // ── WRITE TOOLS ─────────────────────────────────────────────────────────

    [
        'name'        => 'create_lead',
        'description' => 'Create a new lead in the CRM with provided details.',
        'service'     => 'Crm_action_service',
        'method'      => 'create_lead',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['name'],
            'properties' => [
                'name'       => ['type' => 'string',  'description' => 'Full name of the lead'],
                'email'      => ['type' => 'string',  'description' => 'Email address'],
                'phone'      => ['type' => 'string',  'description' => 'Phone number'],
                'company'    => ['type' => 'string',  'description' => 'Company name'],
                'city'       => ['type' => 'string',  'description' => 'City'],
                'state'      => ['type' => 'string',  'description' => 'State'],
                'country'    => ['type' => 'string',  'description' => 'Country'],
                'source'     => ['type' => 'string',  'description' => 'Lead source'],
                'status'     => ['type' => 'integer', 'description' => 'Lead status ID'],
                'assigned_to'=> ['type' => 'integer', 'description' => 'Staff ID to assign'],
                'description'=> ['type' => 'string',  'description' => 'Additional notes'],
            ],
        ],
    ],

    [
        'name'        => 'update_lead',
        'description' => 'Update an existing lead\'s information.',
        'service'     => 'Crm_action_service',
        'method'      => 'update_lead',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['lead_id'],
            'properties' => [
                'lead_id'    => ['type' => 'integer', 'description' => 'Lead ID to update'],
                'name'       => ['type' => 'string',  'description' => 'Updated name'],
                'email'      => ['type' => 'string',  'description' => 'Updated email'],
                'phone'      => ['type' => 'string',  'description' => 'Updated phone'],
                'status'     => ['type' => 'integer', 'description' => 'New status ID'],
                'assigned_to'=> ['type' => 'integer', 'description' => 'Staff ID to assign'],
                'description'=> ['type' => 'string',  'description' => 'Updated notes'],
            ],
        ],
    ],

    [
        'name'        => 'create_task',
        'description' => 'Create a new task and optionally assign it to staff.',
        'service'     => 'Crm_action_service',
        'method'      => 'create_task',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['name'],
            'properties' => [
                'name'        => ['type' => 'string',  'description' => 'Task name'],
                'description' => ['type' => 'string',  'description' => 'Task description'],
                'assigned_to' => ['type' => 'integer', 'description' => 'Staff ID'],
                'due_date'    => ['type' => 'string',  'description' => 'Due date (YYYY-MM-DD)'],
                'priority'    => ['type' => 'string',  'description' => 'low|medium|high|urgent'],
                'rel_type'    => ['type' => 'string',  'description' => 'Related type: project|invoice|lead|customer'],
                'rel_id'      => ['type' => 'integer', 'description' => 'Related record ID'],
                'hourly_rate' => ['type' => 'number',  'description' => 'Hourly rate'],
            ],
        ],
    ],

    [
        'name'        => 'assign_task',
        'description' => 'Assign or reassign an existing task to a staff member.',
        'service'     => 'Crm_action_service',
        'method'      => 'assign_task',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['task_id', 'staff_id'],
            'properties' => [
                'task_id'  => ['type' => 'integer', 'description' => 'Task ID'],
                'staff_id' => ['type' => 'integer', 'description' => 'Staff ID to assign'],
            ],
        ],
    ],

    [
        'name'        => 'add_note',
        'description' => 'Add a note to any CRM entity (lead, client, contract, etc.).',
        'service'     => 'Crm_action_service',
        'method'      => 'add_note',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['rel_type', 'rel_id', 'description'],
            'properties' => [
                'rel_type'    => ['type' => 'string',  'description' => 'Entity type: lead|customer|contract|project'],
                'rel_id'      => ['type' => 'integer', 'description' => 'Entity ID'],
                'description' => ['type' => 'string',  'description' => 'Note content'],
            ],
        ],
    ],

    [
        'name'        => 'create_reminder',
        'description' => 'Create a reminder for the current staff member.',
        'service'     => 'Crm_action_service',
        'method'      => 'create_reminder',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['description', 'date'],
            'properties' => [
                'description' => ['type' => 'string',  'description' => 'Reminder text'],
                'date'        => ['type' => 'string',  'description' => 'Reminder datetime (YYYY-MM-DD HH:MM)'],
                'rel_type'    => ['type' => 'string',  'description' => 'Optional related entity type'],
                'rel_id'      => ['type' => 'integer', 'description' => 'Optional related entity ID'],
                'notify_by_email' => ['type' => 'boolean', 'description' => 'Send email notification'],
            ],
        ],
    ],

    [
        'name'        => 'create_invoice',
        'description' => 'Create a new invoice for a client.',
        'service'     => 'Crm_action_service',
        'method'      => 'create_invoice',
        'permission'  => 'write',
        'confirm'     => true,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['client_id'],
            'properties' => [
                'client_id'    => ['type' => 'integer', 'description' => 'Client ID'],
                'due_date'     => ['type' => 'string',  'description' => 'Due date (YYYY-MM-DD)'],
                'currency'     => ['type' => 'string',  'description' => 'Currency code'],
                'items'        => ['type' => 'array',   'description' => 'Invoice line items', 'items' => ['type' => 'object']],
                'discount'     => ['type' => 'number',  'description' => 'Discount percentage'],
                'notes'        => ['type' => 'string',  'description' => 'Invoice notes'],
                'terms'        => ['type' => 'string',  'description' => 'Payment terms'],
            ],
        ],
    ],

    [
        'name'        => 'create_customer',
        'description' => 'Create a new customer/client in the CRM.',
        'service'     => 'Crm_action_service',
        'method'      => 'create_customer',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['company'],
            'properties' => [
                'company'  => ['type' => 'string',  'description' => 'Company name'],
                'email'    => ['type' => 'string',  'description' => 'Primary email'],
                'phone'    => ['type' => 'string',  'description' => 'Phone number'],
                'website'  => ['type' => 'string',  'description' => 'Website URL'],
                'address'  => ['type' => 'string',  'description' => 'Street address'],
                'city'     => ['type' => 'string',  'description' => 'City'],
                'state'    => ['type' => 'string',  'description' => 'State'],
                'country'  => ['type' => 'string',  'description' => 'Country'],
                'currency' => ['type' => 'string',  'description' => 'Default currency'],
            ],
        ],
    ],

    [
        'name'        => 'update_ticket',
        'description' => 'Update support ticket status, priority, or assignment.',
        'service'     => 'Crm_action_service',
        'method'      => 'update_ticket',
        'permission'  => 'write',
        'confirm'     => false,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['ticket_id'],
            'properties' => [
                'ticket_id'   => ['type' => 'integer', 'description' => 'Ticket ID'],
                'status'      => ['type' => 'integer', 'description' => 'New status ID'],
                'priority'    => ['type' => 'string',  'description' => 'New priority'],
                'assigned_to' => ['type' => 'integer', 'description' => 'Staff ID'],
            ],
        ],
    ],

    [
        'name'        => 'send_email',
        'description' => 'Send an email to a client or lead from the CRM.',
        'service'     => 'Crm_action_service',
        'method'      => 'send_email',
        'permission'  => 'write',
        'confirm'     => true,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['to', 'subject', 'body'],
            'properties' => [
                'to'      => ['type' => 'string', 'description' => 'Recipient email address'],
                'subject' => ['type' => 'string', 'description' => 'Email subject'],
                'body'    => ['type' => 'string', 'description' => 'Email body (HTML allowed)'],
                'cc'      => ['type' => 'string', 'description' => 'CC addresses (comma-separated)'],
            ],
        ],
    ],

    [
        'name'        => 'create_proposal',
        'description' => 'Create a new proposal for a lead or client.',
        'service'     => 'Crm_action_service',
        'method'      => 'create_proposal',
        'permission'  => 'write',
        'confirm'     => true,
        'parameters'  => [
            'type'       => 'object',
            'required'   => ['subject', 'rel_type', 'rel_id'],
            'properties' => [
                'subject'  => ['type' => 'string',  'description' => 'Proposal subject'],
                'rel_type' => ['type' => 'string',  'description' => 'lead|customer'],
                'rel_id'   => ['type' => 'integer', 'description' => 'Lead or client ID'],
                'date'     => ['type' => 'string',  'description' => 'Proposal date (YYYY-MM-DD)'],
                'open_till'=> ['type' => 'string',  'description' => 'Valid until date'],
                'content'  => ['type' => 'string',  'description' => 'Proposal content/body'],
                'currency' => ['type' => 'string',  'description' => 'Currency code'],
                'discount_type' => ['type' => 'string', 'description' => 'percentage|fixed'],
                'discount_total'=> ['type' => 'number',  'description' => 'Discount amount'],
            ],
        ],
    ],

];
