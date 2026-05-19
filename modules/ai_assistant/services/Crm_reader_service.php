<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CRM Reader Service
 *
 * All read-only CRM data access for AI tool calls.
 * NEVER executes raw AI-generated SQL — all queries are parameterized and pre-defined.
 */
class Crm_reader_service
{
    private $CI;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT     = 100;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper('ai_assistant');
    }

    // ── LEADS ─────────────────────────────────────────────────────────────────

    /**
     * Search and filter leads
     */
    public function search_leads(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $this->CI->db->select('l.id, l.name, l.email, l.phonenumber, l.company,
                l.city, l.status, l.source, l.assigned, l.dateadded,
                ls.name as status_name, ls.color as status_color,
                CONCAT(s.firstname, " ", s.lastname) as assigned_name')
            ->from('leads l')
            ->join('leads_status ls', 'ls.id = l.status', 'left')
            ->join('staff s', 's.staffid = l.assigned', 'left')
            ->order_by('l.id', 'DESC')
            ->limit($limit);

        if (!empty($params['keyword'])) {
            $kw = $this->CI->db->escape_like_str($params['keyword']);
            $this->CI->db->group_start()
                ->like('l.name', $kw)
                ->or_like('l.email', $kw)
                ->or_like('l.company', $kw)
                ->or_like('l.phonenumber', $kw)
                ->group_end();
        }

        if (!empty($params['status'])) {
            $this->CI->db->where('l.status', (int)$params['status']);
        }

        if (!empty($params['source'])) {
            $this->CI->db->where('l.source', ai_sanitize_input($params['source']));
        }

        if (!empty($params['assigned_to'])) {
            $this->CI->db->where('l.assigned', (int)$params['assigned_to']);
        }

        if (!empty($params['date_from'])) {
            $this->CI->db->where('l.dateadded >=', $params['date_from']);
        }

        if (!empty($params['date_to'])) {
            $this->CI->db->where('l.dateadded <=', $params['date_to'] . ' 23:59:59');
        }

        $rows = $this->CI->db->get()->result_array();

        return [
            'total'  => count($rows),
            'leads'  => $rows,
            'summary' => $this->build_leads_summary($rows),
        ];
    }

    /**
     * Get full lead details by ID
     */
    public function get_lead_details(array $params): array
    {
        $lead_id = (int)$params['lead_id'];

        $lead = $this->CI->db->select('l.*, ls.name as status_name,
                CONCAT(s.firstname, " ", s.lastname) as assigned_name,
                s.email as assigned_email')
            ->from('leads l')
            ->join('leads_status ls', 'ls.id = l.status', 'left')
            ->join('staff s', 's.staffid = l.assigned', 'left')
            ->where('l.id', $lead_id)
            ->get()
            ->row_array();

        if (!$lead) {
            return ['found' => false, 'error' => "Lead #{$lead_id} not found."];
        }

        // Fetch notes
        $notes = $this->CI->db->select('description, addedfrom, dateadded')
            ->where(['rel_type' => 'lead', 'rel_id' => $lead_id])
            ->order_by('dateadded', 'DESC')
            ->limit(5)
            ->get('notes')
            ->result_array();

        return ['found' => true, 'lead' => $lead, 'notes' => $notes];
    }

    // ── CLIENTS ───────────────────────────────────────────────────────────────

    /**
     * Search clients by keyword, city, country
     */
    public function search_clients(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $this->CI->db->select('c.userid, c.company, c.vat, c.phonenumber, c.city,
                c.country, c.active, c.datecreated,
                c.website, c.currency,
                IFNULL(ce.email, "") as primary_email')
            ->from('clients c')
            ->join('contacts ce', 'ce.userid = c.userid AND ce.is_primary = 1', 'left')
            ->order_by('c.userid', 'DESC')
            ->limit($limit);

        if (!empty($params['keyword'])) {
            $kw = $this->CI->db->escape_like_str($params['keyword']);
            $this->CI->db->group_start()
                ->like('c.company', $kw)
                ->or_like('ce.email', $kw)
                ->or_like('c.phonenumber', $kw)
                ->or_like('c.vat', $kw)
                ->group_end();
        }

        if (!empty($params['city'])) {
            $this->CI->db->like('c.city', $params['city']);
        }

        if (!empty($params['country'])) {
            $this->CI->db->where('c.country', ai_sanitize_input($params['country']));
        }

        $rows = $this->CI->db->get()->result_array();

        return [
            'total'   => count($rows),
            'clients' => $rows,
        ];
    }

    /**
     * Get full client details with summary
     */
    public function get_client_details(array $params): array
    {
        $client_id = (int)$params['client_id'];

        $client = $this->CI->db->get_where('clients', ['userid' => $client_id])->row_array();

        if (!$client) {
            return ['found' => false, 'error' => "Client #{$client_id} not found."];
        }

        // Contacts
        $contacts = $this->CI->db->select('firstname, lastname, email, phonenumber, title, is_primary')
            ->where('userid', $client_id)
            ->get('contacts')
            ->result_array();

        // Invoice summary
        $inv_summary = $this->CI->db->select('
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 2 THEN total ELSE 0 END) as unpaid_amount,
                SUM(CASE WHEN status = 1 THEN total ELSE 0 END) as paid_amount,
                SUM(total) as total_billed')
            ->where('clientid', $client_id)
            ->get('invoices')
            ->row_array();

        // Open tickets
        $open_tickets = $this->CI->db->where(['userid' => $client_id, 'status' => 'open'])
            ->count_all_results('tickets');

        // Active projects
        $active_projects = $this->CI->db->where(['clientid' => $client_id, 'status' => 1])
            ->count_all_results('projects');

        return [
            'found'           => true,
            'client'          => $client,
            'contacts'        => $contacts,
            'invoice_summary' => $inv_summary,
            'open_tickets'    => $open_tickets,
            'active_projects' => $active_projects,
        ];
    }

    // ── INVOICES ──────────────────────────────────────────────────────────────

    /**
     * Get invoices with filters
     */
    public function get_invoice_summary(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $this->CI->db->select('i.id, i.number, i.prefix, i.clientid, i.date, i.duedate,
                i.total, i.subtotal, i.status, i.currency, i.discount_total,
                c.company as client_name,
                CONCAT(i.prefix, i.number) as invoice_number')
            ->from('invoices i')
            ->join('clients c', 'c.userid = i.clientid', 'left')
            ->order_by('i.id', 'DESC')
            ->limit($limit);

        // Status mapping: 1=unpaid, 2=paid, 3=overdue, 4=cancelled, 5=draft
        if (!empty($params['status'])) {
            $status_map = [
                'unpaid'    => 1,
                'paid'      => 2,
                'overdue'   => 3,
                'cancelled' => 4,
                'draft'     => 5,
            ];
            $status_id = $status_map[strtolower($params['status'])] ?? null;
            if ($status_id) {
                $this->CI->db->where('i.status', $status_id);
            }
        }

        if (!empty($params['client_id'])) {
            $this->CI->db->where('i.clientid', (int)$params['client_id']);
        }

        if (!empty($params['min_amount'])) {
            $this->CI->db->where('i.total >=', (float)$params['min_amount']);
        }

        if (!empty($params['max_amount'])) {
            $this->CI->db->where('i.total <=', (float)$params['max_amount']);
        }

        if (!empty($params['date_from'])) {
            $this->CI->db->where('i.date >=', $params['date_from']);
        }

        if (!empty($params['date_to'])) {
            $this->CI->db->where('i.date <=', $params['date_to']);
        }

        $rows = $this->CI->db->get()->result_array();

        // Aggregate totals
        $total_amount  = array_sum(array_column($rows, 'total'));
        $currency_sym  = get_base_currency()->symbol ?? '₹';

        return [
            'total'         => count($rows),
            'invoices'      => $rows,
            'total_amount'  => $total_amount,
            'formatted_total' => ai_format_currency($total_amount, $currency_sym),
        ];
    }

    // ── REVENUE REPORT ────────────────────────────────────────────────────────

    /**
     * Generate revenue report for a period
     */
    public function generate_revenue_report(array $params): array
    {
        $period    = $params['period'] ?? 'month';
        $date_from = !empty($params['date_from']) ? $params['date_from'] : $this->period_start($period);
        $date_to   = !empty($params['date_to'])   ? $params['date_to']   : date('Y-m-d');
        $sym       = get_base_currency()->symbol ?? '₹';

        // Total paid invoices
        $paid = $this->CI->db->select_sum('total')
            ->where('status', 2)
            ->where('date >=', $date_from)
            ->where('date <=', $date_to)
            ->get('invoices')
            ->row();
        $total_revenue = (float)($paid->total ?? 0);

        // Total unpaid / pending
        $unpaid = $this->CI->db->select_sum('total')
            ->where('status', 1)
            ->where('date >=', $date_from)
            ->where('date <=', $date_to)
            ->get('invoices')
            ->row();
        $pending_amount = (float)($unpaid->total ?? 0);

        // Total expenses
        $expenses_row = $this->CI->db->select_sum('amount')
            ->where('date >=', $date_from)
            ->where('date <=', $date_to)
            ->get('expenses')
            ->row();
        $total_expenses = (float)($expenses_row->amount ?? 0);

        // Invoice count by status
        $invoice_counts = $this->CI->db->select('status, COUNT(*) as count, SUM(total) as total')
            ->where('date >=', $date_from)
            ->where('date <=', $date_to)
            ->group_by('status')
            ->get('invoices')
            ->result_array();

        // Top clients by revenue
        $top_clients = $this->CI->db->select('c.company, SUM(i.total) as revenue')
            ->from('invoices i')
            ->join('clients c', 'c.userid = i.clientid', 'left')
            ->where('i.status', 2)
            ->where('i.date >=', $date_from)
            ->where('i.date <=', $date_to)
            ->group_by('i.clientid')
            ->order_by('revenue', 'DESC')
            ->limit(5)
            ->get()
            ->result_array();

        return [
            'period'          => "{$date_from} to {$date_to}",
            'total_revenue'   => $total_revenue,
            'pending_amount'  => $pending_amount,
            'total_expenses'  => $total_expenses,
            'net_profit'      => $total_revenue - $total_expenses,
            'formatted' => [
                'revenue'  => ai_format_currency($total_revenue, $sym),
                'pending'  => ai_format_currency($pending_amount, $sym),
                'expenses' => ai_format_currency($total_expenses, $sym),
                'profit'   => ai_format_currency($total_revenue - $total_expenses, $sym),
            ],
            'invoice_breakdown' => $invoice_counts,
            'top_clients'       => $top_clients,
        ];
    }

    // ── TASKS ─────────────────────────────────────────────────────────────────

    /**
     * Get pending/in-progress tasks
     */
    public function get_pending_tasks(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        // Status: 1=not started, 2=in progress, 3=testing, 4=awaiting feedback, 5=complete
        $this->CI->db->select('t.id, t.name, t.status, t.priority, t.duedate,
                t.rel_type, t.rel_id, t.billable, t.hourly_rate,
                GROUP_CONCAT(DISTINCT CONCAT(s.firstname, " ", s.lastname) SEPARATOR ", ") as assignees,
                CASE t.priority WHEN 1 THEN "Low" WHEN 2 THEN "Medium" WHEN 3 THEN "High" WHEN 4 THEN "Urgent" END as priority_name')
            ->from('tasks t')
            ->join('task_assigned ta', 'ta.taskid = t.id', 'left')
            ->join('staff s', 's.staffid = ta.staffid', 'left')
            ->where_in('t.status', [1, 2, 3, 4])
            ->group_by('t.id')
            ->order_by('t.duedate', 'ASC')
            ->limit($limit);

        if (!empty($params['assigned_to'])) {
            $this->CI->db->where('ta.staffid', (int)$params['assigned_to']);
        }

        if (!empty($params['project_id'])) {
            $this->CI->db->where('t.rel_type', 'project')
                ->where('t.rel_id', (int)$params['project_id']);
        }

        if (!empty($params['priority'])) {
            $prio_map = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
            $prio_id  = $prio_map[strtolower($params['priority'])] ?? null;
            if ($prio_id) {
                $this->CI->db->where('t.priority', $prio_id);
            }
        }

        if (!empty($params['overdue'])) {
            $this->CI->db->where('t.duedate <', date('Y-m-d'))
                ->where('t.duedate IS NOT NULL');
        }

        $rows = $this->CI->db->get()->result_array();

        return [
            'total' => count($rows),
            'tasks' => $rows,
        ];
    }

    // ── PROJECTS ──────────────────────────────────────────────────────────────

    /**
     * Search projects
     */
    public function search_projects(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $this->CI->db->select('p.id, p.name, p.status, p.deadline, p.start_date,
                p.project_cost, p.progress, p.billing_type,
                c.company as client_name, p.clientid')
            ->from('projects p')
            ->join('clients c', 'c.userid = p.clientid', 'left')
            ->order_by('p.id', 'DESC')
            ->limit($limit);

        if (!empty($params['keyword'])) {
            $this->CI->db->like('p.name', $params['keyword']);
        }

        if (!empty($params['status'])) {
            $this->CI->db->where('p.status', (int)$params['status']);
        }

        if (!empty($params['client_id'])) {
            $this->CI->db->where('p.clientid', (int)$params['client_id']);
        }

        if (!empty($params['member_id'])) {
            $this->CI->db->join('project_members pm', "pm.project_id = p.id AND pm.staff_id = " . (int)$params['member_id'], 'inner');
        }

        $rows = $this->CI->db->get()->result_array();

        return ['total' => count($rows), 'projects' => $rows];
    }

    // ── TICKETS ───────────────────────────────────────────────────────────────

    /**
     * Get support tickets
     */
    public function get_ticket_summary(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $this->CI->db->select('t.ticketid, t.subject, t.status, t.priority, t.date,
                t.lastreply, t.userid, t.assigned,
                c.company as client_name,
                CONCAT(s.firstname, " ", s.lastname) as assigned_name')
            ->from('tickets t')
            ->join('clients c', 'c.userid = t.userid', 'left')
            ->join('staff s', 's.staffid = t.assigned', 'left')
            ->order_by('t.date', 'DESC')
            ->limit($limit);

        if (!empty($params['status'])) {
            $this->CI->db->where('t.status', ai_sanitize_input($params['status']));
        }

        if (!empty($params['priority'])) {
            $this->CI->db->where('t.priority', ai_sanitize_input($params['priority']));
        }

        if (!empty($params['client_id'])) {
            $this->CI->db->where('t.userid', (int)$params['client_id']);
        }

        if (!empty($params['department'])) {
            $this->CI->db->where('t.department', (int)$params['department']);
        }

        $rows = $this->CI->db->get()->result_array();

        return ['total' => count($rows), 'tickets' => $rows];
    }

    // ── STAFF ─────────────────────────────────────────────────────────────────

    /**
     * Get staff list
     */
    public function get_staff_list(array $params): array
    {
        $this->CI->db->select('staffid, firstname, lastname, email, phonenumber, position, active');

        if (!empty($params['active_only'])) {
            $this->CI->db->where('active', 1);
        }

        if (!empty($params['keyword'])) {
            $kw = $this->CI->db->escape_like_str($params['keyword']);
            $this->CI->db->group_start()
                ->like('firstname', $kw)
                ->or_like('lastname', $kw)
                ->or_like('email', $kw)
                ->group_end();
        }

        $rows = $this->CI->db->get('staff')->result_array();

        return ['total' => count($rows), 'staff' => $rows];
    }

    // ── EXPENSES ──────────────────────────────────────────────────────────────

    /**
     * Get expense summary
     */
    public function get_expense_summary(array $params): array
    {
        $period    = $params['period'] ?? 'month';
        $date_from = !empty($params['date_from']) ? $params['date_from'] : $this->period_start($period);
        $date_to   = !empty($params['date_to'])   ? $params['date_to']   : date('Y-m-d');
        $sym       = get_base_currency()->symbol ?? '₹';

        $this->CI->db->select('e.*, ec.name as category_name')
            ->from('expenses e')
            ->join('expenses_categories ec', 'ec.id = e.category', 'left')
            ->where('e.date >=', $date_from)
            ->where('e.date <=', $date_to);

        if (!empty($params['category'])) {
            $this->CI->db->where('e.category', (int)$params['category']);
        }

        $rows = $this->CI->db->get()->result_array();

        $total = array_sum(array_column($rows, 'amount'));

        // Group by category
        $by_category = [];
        foreach ($rows as $row) {
            $cat = $row['category_name'] ?? 'Uncategorized';
            $by_category[$cat] = ($by_category[$cat] ?? 0) + (float)$row['amount'];
        }
        arsort($by_category);

        return [
            'period'      => "{$date_from} to {$date_to}",
            'total'       => $total,
            'formatted'   => ai_format_currency($total, $sym),
            'by_category' => $by_category,
            'expenses'    => array_slice($rows, 0, 20),
        ];
    }

    // ── CONTRACTS ─────────────────────────────────────────────────────────────

    /**
     * Get contracts with filters
     */
    public function get_contracts(array $params): array
    {
        $limit = min((int)($params['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $this->CI->db->select('co.id, co.subject, co.datestart, co.dateend, co.value,
                co.status, co.signed, co.trash,
                c.company as client_name')
            ->from('contracts co')
            ->join('clients c', 'c.userid = co.client', 'left')
            ->where('co.trash', 0)
            ->order_by('co.id', 'DESC')
            ->limit($limit);

        if (!empty($params['client_id'])) {
            $this->CI->db->where('co.client', (int)$params['client_id']);
        }

        if (!empty($params['status'])) {
            $this->CI->db->where('co.status', (int)$params['status']);
        }

        if (!empty($params['expiring_soon'])) {
            $thirty_days = date('Y-m-d', strtotime('+30 days'));
            $this->CI->db->where('co.dateend <=', $thirty_days)
                ->where('co.dateend >=', date('Y-m-d'));
        }

        $rows = $this->CI->db->get()->result_array();

        return ['total' => count($rows), 'contracts' => $rows];
    }

    // ── Internal Helpers ─────────────────────────────────────────────────────

    private function period_start(string $period): string
    {
        switch ($period) {
            case 'today':
                return date('Y-m-d');
            case 'week':
                return date('Y-m-d', strtotime('monday this week'));
            case 'month':
                return date('Y-m-01');
            case 'quarter':
                return date('Y-m-d', strtotime('first day of -3 month'));
            case 'year':
                return date('Y-01-01');
            default:
                return date('Y-m-01');
        }
    }

    private function build_leads_summary(array $rows): string
    {
        if (empty($rows)) {
            return 'No leads found.';
        }
        return sprintf('%d lead(s) found. Most recent: %s', count($rows), $rows[0]['name'] ?? '');
    }
}
