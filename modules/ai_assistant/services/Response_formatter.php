<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Response Formatter
 * Converts raw tool results into human-readable AI-ready messages.
 */
class Response_formatter
{
    private string $currency_sym;

    public function __construct()
    {
        $this->currency_sym = get_base_currency()->symbol ?? '₹';
    }

    /**
     * Format a tool result into a readable text block for the AI to present
     *
     * @param  string $tool_name
     * @param  array  $result   Raw result from service
     * @return string
     */
    public function format(string $tool_name, array $result): string
    {
        if (!($result['success'] ?? true) && !empty($result['error'])) {
            return "**Error:** " . $result['error'];
        }

        return match(true) {
            str_contains($tool_name, 'leads')        => $this->format_leads($result),
            str_contains($tool_name, 'lead')         => $this->format_lead_detail($result),
            str_contains($tool_name, 'clients')      => $this->format_clients($result),
            str_contains($tool_name, 'client_details') => $this->format_client_details($result),
            str_contains($tool_name, 'invoice')      => $this->format_invoices($result),
            str_contains($tool_name, 'revenue')      => $this->format_revenue_report($result),
            str_contains($tool_name, 'tasks')        => $this->format_tasks($result),
            str_contains($tool_name, 'projects')     => $this->format_projects($result),
            str_contains($tool_name, 'ticket')       => $this->format_tickets($result),
            str_contains($tool_name, 'expense')      => $this->format_expenses($result),
            str_contains($tool_name, 'contracts')    => $this->format_contracts($result),
            str_contains($tool_name, 'staff')        => $this->format_staff($result),
            default                                  => $this->format_action_result($result),
        };
    }

    // ── Formatters ───────────────────────────────────────────────────────────

    private function format_leads(array $result): string
    {
        $leads = $result['leads'] ?? [];
        if (empty($leads)) {
            return 'No leads found matching your criteria.';
        }

        $total = $result['total'] ?? count($leads);
        $lines = ["**Found {$total} lead(s):**\n"];

        foreach (array_slice($leads, 0, 15) as $i => $lead) {
            $assigned = $lead['assigned_name'] ? " (Assigned: {$lead['assigned_name']})" : '';
            $phone    = $lead['phonenumber'] ? " | {$lead['phonenumber']}" : '';
            $lines[]  = ($i + 1) . ". **{$lead['name']}** — {$lead['status_name']}{$phone}{$assigned}";
            if (!empty($lead['company'])) {
                $lines[] = "   Company: {$lead['company']}";
            }
        }

        if ($total > 15) {
            $lines[] = "\n_...and " . ($total - 15) . " more._";
        }

        return implode("\n", $lines);
    }

    private function format_lead_detail(array $result): string
    {
        if (!($result['found'] ?? false)) {
            return $result['error'] ?? 'Lead not found.';
        }

        $l    = $result['lead'];
        $name = $l['name'] ?? 'Unknown';
        $lines = [
            "### Lead: {$name}",
            "- **Status:** " . ($l['status_name'] ?? 'N/A'),
            "- **Email:** " . ($l['email'] ?? 'N/A'),
            "- **Phone:** " . ($l['phonenumber'] ?? 'N/A'),
            "- **Company:** " . ($l['company'] ?? 'N/A'),
            "- **City:** " . ($l['city'] ?? 'N/A'),
            "- **Assigned to:** " . ($l['assigned_name'] ?? 'N/A'),
            "- **Added:** " . ($l['dateadded'] ?? 'N/A'),
        ];

        if (!empty($l['description'])) {
            $lines[] = "- **Description:** " . mb_substr($l['description'], 0, 200);
        }

        $notes = $result['notes'] ?? [];
        if (!empty($notes)) {
            $lines[] = "\n**Recent Notes:**";
            foreach ($notes as $note) {
                $lines[] = "- " . mb_substr($note['description'], 0, 100) . " _(added {$note['dateadded']})_";
            }
        }

        return implode("\n", $lines);
    }

    private function format_clients(array $result): string
    {
        $clients = $result['clients'] ?? [];
        if (empty($clients)) {
            return 'No clients found.';
        }

        $total = $result['total'] ?? count($clients);
        $lines = ["**Found {$total} client(s):**\n"];

        foreach (array_slice($clients, 0, 15) as $i => $c) {
            $email = $c['primary_email'] ? " | {$c['primary_email']}" : '';
            $city  = $c['city'] ? " | {$c['city']}" : '';
            $lines[] = ($i + 1) . ". **{$c['company']}** (ID: {$c['userid']}){$email}{$city}";
        }

        return implode("\n", $lines);
    }

    private function format_client_details(array $result): string
    {
        if (!($result['found'] ?? false)) {
            return $result['error'] ?? 'Client not found.';
        }

        $c    = $result['client'];
        $inv  = $result['invoice_summary'] ?? [];
        $sym  = $this->currency_sym;
        $lines = [
            "### Client: {$c['company']}",
            "- **Phone:** " . ($c['phonenumber'] ?? 'N/A'),
            "- **City:** " . ($c['city'] ?? 'N/A'),
            "- **Active:** " . ($c['active'] ? 'Yes' : 'No'),
            "",
            "**Business Summary:**",
            "- Total Invoiced: {$sym}" . number_format((float)($inv['total_billed'] ?? 0), 2),
            "- Paid: {$sym}" . number_format((float)($inv['paid_amount'] ?? 0), 2),
            "- Unpaid: {$sym}" . number_format((float)($inv['unpaid_amount'] ?? 0), 2),
            "- Open Tickets: " . ($result['open_tickets'] ?? 0),
            "- Active Projects: " . ($result['active_projects'] ?? 0),
        ];

        $contacts = $result['contacts'] ?? [];
        if (!empty($contacts)) {
            $lines[] = "\n**Contacts:**";
            foreach (array_slice($contacts, 0, 5) as $contact) {
                $primary = $contact['is_primary'] ? ' (Primary)' : '';
                $lines[] = "- {$contact['firstname']} {$contact['lastname']}{$primary} — {$contact['email']}";
            }
        }

        return implode("\n", $lines);
    }

    private function format_invoices(array $result): string
    {
        $invoices = $result['invoices'] ?? [];
        if (empty($invoices)) {
            return 'No invoices found.';
        }

        $total  = $result['total'] ?? count($invoices);
        $amount = $result['formatted_total'] ?? '';
        $sym    = $this->currency_sym;

        $status_map = [1 => 'Unpaid', 2 => 'Paid', 3 => 'Overdue', 4 => 'Cancelled', 5 => 'Draft'];

        $lines = ["**Found {$total} invoice(s)** — Total: **{$amount}**\n"];

        foreach (array_slice($invoices, 0, 15) as $inv) {
            $status = $status_map[$inv['status']] ?? 'Unknown';
            $amount = $sym . number_format((float)$inv['total'], 2);
            $due    = $inv['duedate'] ? date('d M Y', strtotime($inv['duedate'])) : 'N/A';
            $lines[] = "- **{$inv['invoice_number']}** | {$inv['client_name']} | {$amount} | {$status} | Due: {$due}";
        }

        return implode("\n", $lines);
    }

    private function format_revenue_report(array $result): string
    {
        $f = $result['formatted'] ?? [];
        $lines = [
            "### Revenue Report: {$result['period']}",
            "",
            "| Metric | Amount |",
            "|--------|--------|",
            "| **Total Revenue (Paid)** | {$f['revenue']} |",
            "| **Pending Amount** | {$f['pending']} |",
            "| **Total Expenses** | {$f['expenses']} |",
            "| **Net Profit** | {$f['profit']} |",
        ];

        $top_clients = $result['top_clients'] ?? [];
        if (!empty($top_clients)) {
            $lines[] = "\n**Top Clients by Revenue:**";
            foreach ($top_clients as $i => $c) {
                $rev = $this->currency_sym . number_format((float)$c['revenue'], 2);
                $lines[] = ($i + 1) . ". {$c['company']}: {$rev}";
            }
        }

        return implode("\n", $lines);
    }

    private function format_tasks(array $result): string
    {
        $tasks = $result['tasks'] ?? [];
        if (empty($tasks)) {
            return 'No pending tasks found.';
        }

        $total = $result['total'] ?? count($tasks);
        $lines = ["**{$total} pending task(s):**\n"];

        foreach (array_slice($tasks, 0, 15) as $task) {
            $due      = $task['duedate'] ? date('d M Y', strtotime($task['duedate'])) : 'No due date';
            $priority = $task['priority_name'] ?? 'Medium';
            $assigned = $task['assignees'] ? " → {$task['assignees']}" : ' → Unassigned';
            $overdue  = ($task['duedate'] && strtotime($task['duedate']) < time()) ? ' ⚠️ OVERDUE' : '';
            $lines[]  = "- **{$task['name']}**{$overdue}  \n  Priority: {$priority} | Due: {$due}{$assigned}";
        }

        return implode("\n", $lines);
    }

    private function format_projects(array $result): string
    {
        $projects = $result['projects'] ?? [];
        if (empty($projects)) {
            return 'No projects found.';
        }

        $total = $result['total'] ?? count($projects);
        $lines = ["**{$total} project(s):**\n"];

        $status_map = [1 => 'In Progress', 2 => 'On Hold', 3 => 'Cancelled', 4 => 'Finished'];

        foreach (array_slice($projects, 0, 10) as $p) {
            $status   = $status_map[$p['status']] ?? 'Unknown';
            $deadline = $p['deadline'] ? date('d M Y', strtotime($p['deadline'])) : 'No deadline';
            $progress = isset($p['progress']) ? " | {$p['progress']}%" : '';
            $lines[]  = "- **{$p['name']}** ({$p['client_name']}) — {$status} | Deadline: {$deadline}{$progress}";
        }

        return implode("\n", $lines);
    }

    private function format_tickets(array $result): string
    {
        $tickets = $result['tickets'] ?? [];
        if (empty($tickets)) {
            return 'No tickets found.';
        }

        $total = $result['total'] ?? count($tickets);
        $lines = ["**{$total} ticket(s):**\n"];

        foreach (array_slice($tickets, 0, 15) as $t) {
            $assigned = $t['assigned_name'] ? " → {$t['assigned_name']}" : '';
            $lines[]  = "- **[{$t['status']}]** {$t['subject']} ({$t['client_name']}){$assigned}";
        }

        return implode("\n", $lines);
    }

    private function format_expenses(array $result): string
    {
        $lines = [
            "### Expense Summary: {$result['period']}",
            "**Total Expenses: {$result['formatted']}**\n",
        ];

        $by_cat = $result['by_category'] ?? [];
        if (!empty($by_cat)) {
            $lines[] = "**By Category:**";
            foreach ($by_cat as $cat => $amount) {
                $lines[] = "- {$cat}: " . $this->currency_sym . number_format($amount, 2);
            }
        }

        return implode("\n", $lines);
    }

    private function format_contracts(array $result): string
    {
        $contracts = $result['contracts'] ?? [];
        if (empty($contracts)) {
            return 'No contracts found.';
        }

        $total = $result['total'] ?? count($contracts);
        $lines = ["**{$total} contract(s):**\n"];

        foreach (array_slice($contracts, 0, 10) as $c) {
            $sym    = $this->currency_sym;
            $value  = $c['value'] ? " | {$sym}" . number_format((float)$c['value'], 2) : '';
            $end    = $c['dateend'] ? date('d M Y', strtotime($c['dateend'])) : 'No end date';
            $signed = $c['signed'] ? ' ✓ Signed' : '';
            $lines[] = "- **{$c['subject']}** ({$c['client_name']}){$value} | Expires: {$end}{$signed}";
        }

        return implode("\n", $lines);
    }

    private function format_staff(array $result): string
    {
        $staff = $result['staff'] ?? [];
        if (empty($staff)) {
            return 'No staff found.';
        }

        $total = $result['total'] ?? count($staff);
        $lines = ["**{$total} staff member(s):**\n"];

        foreach ($staff as $s) {
            $position = $s['position'] ? " — {$s['position']}" : '';
            $lines[]  = "- **{$s['firstname']} {$s['lastname']}** (ID: {$s['staffid']}){$position} | {$s['email']}";
        }

        return implode("\n", $lines);
    }

    private function format_action_result(array $result): string
    {
        if (!empty($result['message'])) {
            return $result['message'];
        }

        if ($result['success'] ?? false) {
            return '✓ Action completed successfully.';
        }

        return '✗ Action failed: ' . ($result['error'] ?? 'Unknown error');
    }
}
