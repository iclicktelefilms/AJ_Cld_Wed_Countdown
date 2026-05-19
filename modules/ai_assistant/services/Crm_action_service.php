<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CRM Action Service
 *
 * Executes all write/create/update operations triggered by AI.
 * All data is sanitized and validated before DB operations.
 * Uses Perfex CRM models/helpers wherever available.
 */
class Crm_action_service
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper('ai_assistant');
    }

    // ── LEADS ─────────────────────────────────────────────────────────────────

    /**
     * Create a new lead
     */
    public function create_lead(array $params): array
    {
        $this->CI->load->model('leads_model');

        $data = [
            'name'        => ai_sanitize_input($params['name'] ?? ''),
            'email'       => filter_var($params['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'phonenumber' => ai_sanitize_input($params['phone'] ?? ''),
            'company'     => ai_sanitize_input($params['company'] ?? ''),
            'city'        => ai_sanitize_input($params['city'] ?? ''),
            'state'       => ai_sanitize_input($params['state'] ?? ''),
            'country'     => ai_sanitize_input($params['country'] ?? ''),
            'source'      => ai_sanitize_input($params['source'] ?? ''),
            'status'      => (int)($params['status'] ?? 1),
            'assigned'    => (int)($params['assigned_to'] ?? get_staff_user_id()),
            'description' => ai_sanitize_input($params['description'] ?? ''),
            'addedfrom'   => get_staff_user_id(),
            'dateadded'   => date('Y-m-d H:i:s'),
            'is_public'   => 1,
            'lastcontact' => null,
            'lost'        => 0,
            'junk'        => 0,
            'converted'   => 0,
        ];

        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Lead name is required.'];
        }

        $lead_id = $this->CI->leads_model->add($data);

        if (!$lead_id) {
            return ['success' => false, 'error' => 'Failed to create lead.'];
        }

        return [
            'success'  => true,
            'id'       => $lead_id,
            'message'  => "Lead '{$data['name']}' created successfully (ID: {$lead_id}).",
        ];
    }

    /**
     * Update an existing lead
     */
    public function update_lead(array $params): array
    {
        $lead_id = (int)($params['lead_id'] ?? 0);

        if (!$lead_id) {
            return ['success' => false, 'error' => 'Lead ID is required.'];
        }

        $this->CI->load->model('leads_model');

        // Fetch current lead to verify it exists
        $lead = $this->CI->db->get_where('leads', ['id' => $lead_id])->row_array();
        if (!$lead) {
            return ['success' => false, 'error' => "Lead #{$lead_id} not found."];
        }

        $update = [];
        if (!empty($params['name']))        $update['name']        = ai_sanitize_input($params['name']);
        if (!empty($params['email']))       $update['email']       = filter_var($params['email'], FILTER_SANITIZE_EMAIL);
        if (!empty($params['phone']))       $update['phonenumber'] = ai_sanitize_input($params['phone']);
        if (isset($params['status']))       $update['status']      = (int)$params['status'];
        if (!empty($params['assigned_to'])) $update['assigned']    = (int)$params['assigned_to'];
        if (!empty($params['description'])) $update['description'] = ai_sanitize_input($params['description']);

        if (empty($update)) {
            return ['success' => false, 'error' => 'No valid fields to update.'];
        }

        $result = $this->CI->leads_model->update($update, $lead_id);

        return [
            'success' => (bool)$result,
            'id'      => $lead_id,
            'message' => $result ? "Lead #{$lead_id} updated successfully." : 'Update failed.',
        ];
    }

    // ── TASKS ─────────────────────────────────────────────────────────────────

    /**
     * Create a new task
     */
    public function create_task(array $params): array
    {
        $this->CI->load->model('tasks_model');

        $priority_map = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
        $priority     = $priority_map[strtolower($params['priority'] ?? 'medium')] ?? 2;

        $data = [
            'name'         => ai_sanitize_input($params['name'] ?? ''),
            'description'  => ai_sanitize_input($params['description'] ?? ''),
            'priority'     => $priority,
            'status'       => 1, // Not started
            'duedate'      => !empty($params['due_date']) ? date('Y-m-d', strtotime($params['due_date'])) : null,
            'rel_type'     => ai_sanitize_input($params['rel_type'] ?? ''),
            'rel_id'       => !empty($params['rel_id']) ? (int)$params['rel_id'] : null,
            'billable'     => 1,
            'hourly_rate'  => !empty($params['hourly_rate']) ? (float)$params['hourly_rate'] : 0,
            'dateadded'    => date('Y-m-d H:i:s'),
            'addedfrom'    => get_staff_user_id(),
        ];

        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Task name is required.'];
        }

        $task_id = $this->CI->tasks_model->add($data);

        if (!$task_id) {
            return ['success' => false, 'error' => 'Failed to create task.'];
        }

        // Assign staff if provided
        if (!empty($params['assigned_to'])) {
            $this->CI->tasks_model->add_task_assignees(
                $task_id,
                [(int)$params['assigned_to']]
            );
        }

        return [
            'success' => true,
            'id'      => $task_id,
            'message' => "Task '{$data['name']}' created successfully (ID: {$task_id}).",
        ];
    }

    /**
     * Assign a task to a staff member
     */
    public function assign_task(array $params): array
    {
        $task_id  = (int)($params['task_id'] ?? 0);
        $staff_id = (int)($params['staff_id'] ?? 0);

        if (!$task_id || !$staff_id) {
            return ['success' => false, 'error' => 'Both task_id and staff_id are required.'];
        }

        $this->CI->load->model('tasks_model');

        // Verify task exists
        $task = $this->CI->db->get_where('tasks', ['id' => $task_id])->row_array();
        if (!$task) {
            return ['success' => false, 'error' => "Task #{$task_id} not found."];
        }

        // Verify staff exists
        $staff = get_staff($staff_id);
        if (!$staff) {
            return ['success' => false, 'error' => "Staff #{$staff_id} not found."];
        }

        $this->CI->tasks_model->add_task_assignees($task_id, [$staff_id]);

        return [
            'success' => true,
            'message' => "Task #{$task_id} assigned to {$staff->firstname} {$staff->lastname}.",
        ];
    }

    // ── NOTES ─────────────────────────────────────────────────────────────────

    /**
     * Add a note to a CRM entity
     */
    public function add_note(array $params): array
    {
        $rel_type    = ai_sanitize_input($params['rel_type'] ?? '');
        $rel_id      = (int)($params['rel_id'] ?? 0);
        $description = ai_sanitize_input($params['description'] ?? '');

        $allowed_types = ['lead', 'customer', 'contract', 'project', 'invoice', 'estimate', 'expense', 'proposal', 'ticket', 'task'];

        if (!in_array($rel_type, $allowed_types, true)) {
            return ['success' => false, 'error' => "Invalid rel_type. Allowed: " . implode(', ', $allowed_types)];
        }

        if (!$rel_id || empty($description)) {
            return ['success' => false, 'error' => 'rel_id and description are required.'];
        }

        $note_id = $this->CI->db->insert('notes', [
            'rel_type'    => $rel_type,
            'rel_id'      => $rel_id,
            'description' => $description,
            'addedfrom'   => get_staff_user_id(),
            'dateadded'   => date('Y-m-d H:i:s'),
        ]);

        $note_id = $this->CI->db->insert_id();

        return [
            'success' => (bool)$note_id,
            'id'      => $note_id,
            'message' => $note_id ? "Note added to {$rel_type} #{$rel_id}." : 'Failed to add note.',
        ];
    }

    // ── REMINDERS ─────────────────────────────────────────────────────────────

    /**
     * Create a reminder for the current staff member
     */
    public function create_reminder(array $params): array
    {
        $description = ai_sanitize_input($params['description'] ?? '');
        $date_raw    = $params['date'] ?? '';

        if (empty($description) || empty($date_raw)) {
            return ['success' => false, 'error' => 'Description and date are required.'];
        }

        // Parse the date (handles natural language too)
        $parsed_date = ai_parse_relative_time($date_raw) ?? date('Y-m-d H:i:s', strtotime($date_raw));

        if (!$parsed_date) {
            return ['success' => false, 'error' => "Invalid date format: {$date_raw}"];
        }

        $staff_id = get_staff_user_id();

        $data = [
            'description'     => $description,
            'date'            => $parsed_date,
            'addedfrom'       => $staff_id,
            'staff'           => $staff_id,
            'rel_type'        => ai_sanitize_input($params['rel_type'] ?? ''),
            'rel_id'          => !empty($params['rel_id']) ? (int)$params['rel_id'] : null,
            'notify_by_email' => !empty($params['notify_by_email']) ? 1 : 0,
            'creator'         => $staff_id,
            'is_notified'     => 0,
        ];

        $this->CI->db->insert('reminders', $data);
        $reminder_id = $this->CI->db->insert_id();

        return [
            'success'  => (bool)$reminder_id,
            'id'       => $reminder_id,
            'message'  => "Reminder set for " . date('d M Y H:i', strtotime($parsed_date)) . ".",
            'date'     => $parsed_date,
        ];
    }

    // ── INVOICES ──────────────────────────────────────────────────────────────

    /**
     * Create a new invoice
     */
    public function create_invoice(array $params): array
    {
        $client_id = (int)($params['client_id'] ?? 0);

        if (!$client_id) {
            return ['success' => false, 'error' => 'Client ID is required.'];
        }

        $this->CI->load->model('invoices_model');

        $due_date = !empty($params['due_date'])
            ? date('Y-m-d', strtotime($params['due_date']))
            : date('Y-m-d', strtotime('+30 days'));

        $data = [
            'clientid'       => $client_id,
            'date'           => date('Y-m-d'),
            'duedate'        => $due_date,
            'currency'       => ai_sanitize_input($params['currency'] ?? get_base_currency()->id ?? '1'),
            'status'         => 1, // Unpaid
            'adminnote'      => '',
            'notes'          => ai_sanitize_input($params['notes'] ?? ''),
            'terms'          => ai_sanitize_input($params['terms'] ?? ''),
            'discount_total' => (float)($params['discount'] ?? 0),
            'discount_type'  => 'percentage',
            'items'          => $params['items'] ?? [],
        ];

        $invoice_id = $this->CI->invoices_model->add($data);

        if (!$invoice_id) {
            return ['success' => false, 'error' => 'Failed to create invoice.'];
        }

        return [
            'success' => true,
            'id'      => $invoice_id,
            'message' => "Invoice created successfully (ID: {$invoice_id}).",
        ];
    }

    // ── CUSTOMERS ─────────────────────────────────────────────────────────────

    /**
     * Create a new customer/client
     */
    public function create_customer(array $params): array
    {
        $company = ai_sanitize_input($params['company'] ?? '');

        if (empty($company)) {
            return ['success' => false, 'error' => 'Company name is required.'];
        }

        $this->CI->load->model('clients_model');

        $data = [
            'company'  => $company,
            'vat'      => '',
            'phonenumber' => ai_sanitize_input($params['phone'] ?? ''),
            'website'  => filter_var($params['website'] ?? '', FILTER_SANITIZE_URL),
            'address'  => ai_sanitize_input($params['address'] ?? ''),
            'city'     => ai_sanitize_input($params['city'] ?? ''),
            'state'    => ai_sanitize_input($params['state'] ?? ''),
            'country'  => ai_sanitize_input($params['country'] ?? ''),
            'active'   => 1,
            'currency' => ai_sanitize_input($params['currency'] ?? ''),
        ];

        $client_id = $this->CI->clients_model->add($data);

        if (!$client_id) {
            return ['success' => false, 'error' => 'Failed to create customer.'];
        }

        // Add primary contact if email provided
        if (!empty($params['email'])) {
            $email = filter_var($params['email'], FILTER_SANITIZE_EMAIL);
            if ($email) {
                $this->CI->db->insert('contacts', [
                    'userid'     => $client_id,
                    'email'      => $email,
                    'firstname'  => explode(' ', $company)[0],
                    'lastname'   => '',
                    'is_primary' => 1,
                    'active'     => 1,
                ]);
            }
        }

        return [
            'success' => true,
            'id'      => $client_id,
            'message' => "Customer '{$company}' created (ID: {$client_id}).",
        ];
    }

    // ── TICKETS ───────────────────────────────────────────────────────────────

    /**
     * Update a support ticket
     */
    public function update_ticket(array $params): array
    {
        $ticket_id = (int)($params['ticket_id'] ?? 0);

        if (!$ticket_id) {
            return ['success' => false, 'error' => 'Ticket ID is required.'];
        }

        $ticket = $this->CI->db->get_where('tickets', ['ticketid' => $ticket_id])->row_array();
        if (!$ticket) {
            return ['success' => false, 'error' => "Ticket #{$ticket_id} not found."];
        }

        $update = [];
        if (!empty($params['status']))      $update['status']   = (int)$params['status'];
        if (!empty($params['priority']))    $update['priority'] = ai_sanitize_input($params['priority']);
        if (!empty($params['assigned_to'])) $update['assigned'] = (int)$params['assigned_to'];

        if (empty($update)) {
            return ['success' => false, 'error' => 'No fields to update.'];
        }

        $this->CI->db->where('ticketid', $ticket_id)->update('tickets', $update);

        return [
            'success' => $this->CI->db->affected_rows() > 0,
            'id'      => $ticket_id,
            'message' => "Ticket #{$ticket_id} updated successfully.",
        ];
    }

    // ── EMAIL ─────────────────────────────────────────────────────────────────

    /**
     * Send an email from CRM
     */
    public function send_email(array $params): array
    {
        $to      = filter_var($params['to'] ?? '', FILTER_SANITIZE_EMAIL);
        $subject = ai_sanitize_input($params['subject'] ?? '');
        $body    = $params['body'] ?? '';

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid email address: {$to}"];
        }

        if (empty($subject) || empty($body)) {
            return ['success' => false, 'error' => 'Subject and body are required.'];
        }

        // Use Perfex mailer
        $this->CI->load->library('app_mail');

        $mail_result = send_mail_template('', $to, $subject, [], $body);

        // Fallback: use CodeIgniter email
        if (!$mail_result) {
            $this->CI->load->library('email');
            $this->CI->email->from(get_option('smtp_email'), get_option('companyname'));
            $this->CI->email->to($to);
            if (!empty($params['cc'])) {
                $this->CI->email->cc(filter_var($params['cc'], FILTER_SANITIZE_EMAIL));
            }
            $this->CI->email->subject($subject);
            $this->CI->email->message($body);
            $mail_result = $this->CI->email->send();
        }

        return [
            'success' => (bool)$mail_result,
            'message' => $mail_result ? "Email sent to {$to}." : 'Failed to send email.',
        ];
    }

    // ── PROPOSALS ─────────────────────────────────────────────────────────────

    /**
     * Create a new proposal
     */
    public function create_proposal(array $params): array
    {
        $subject  = ai_sanitize_input($params['subject'] ?? '');
        $rel_type = ai_sanitize_input($params['rel_type'] ?? 'lead');
        $rel_id   = (int)($params['rel_id'] ?? 0);

        if (empty($subject) || !$rel_id) {
            return ['success' => false, 'error' => 'Subject and rel_id are required.'];
        }

        if (!in_array($rel_type, ['lead', 'customer'], true)) {
            return ['success' => false, 'error' => 'rel_type must be lead or customer.'];
        }

        $this->CI->load->model('proposals_model');

        $data = [
            'subject'        => $subject,
            'rel_type'       => $rel_type,
            'rel_id'         => $rel_id,
            'date'           => !empty($params['date']) ? date('Y-m-d', strtotime($params['date'])) : date('Y-m-d'),
            'open_till'      => !empty($params['open_till']) ? date('Y-m-d', strtotime($params['open_till'])) : date('Y-m-d', strtotime('+30 days')),
            'content'        => $params['content'] ?? '',
            'currency'       => ai_sanitize_input($params['currency'] ?? ''),
            'discount_type'  => ai_sanitize_input($params['discount_type'] ?? 'percentage'),
            'discount_total' => (float)($params['discount_total'] ?? 0),
            'status'         => 1, // Draft
            'addedfrom'      => get_staff_user_id(),
        ];

        $proposal_id = $this->CI->proposals_model->add($data);

        if (!$proposal_id) {
            return ['success' => false, 'error' => 'Failed to create proposal.'];
        }

        return [
            'success' => true,
            'id'      => $proposal_id,
            'message' => "Proposal '{$subject}' created (ID: {$proposal_id}).",
        ];
    }
}
