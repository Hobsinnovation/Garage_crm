<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Overview {

    /**
     * Premium garage command-center dashboard.
     * Existing database schema is intentionally preserved.
     */
    public function get() {

        $title = 'Dashboard';
        $user = Auth::user();

        if ($user->role == "Booking Manager") {
            redirect(url("Projects@get"));
        } elseif ($user->role == "Inventory Manager") {
            redirect(url("Inventory@get"));
        }

        $widgets = $this->widgets($user);
        $projects = $this->projects($user);
        $tasks = $this->tasks($user);
        $income = $this->twelve($user);
        $recent = $this->recent($user);

        return view('overview', compact("user", "title", "widgets", "projects", "tasks", "income", "recent"));
    }

    public function projects($user) {
        $projects = array();
        $projects["progress"] = Database::table('projects')->where('company', $user->company)->where('status', "In Progress")->count("id", "total")[0]->total;
        $projects["complete"] = Database::table('projects')->where('company', $user->company)->where('status', "Completed")->count("id", "total")[0]->total;
        $projects["bookedin"] = Database::table('projects')->where('company', $user->company)->where('status', "Booked In")->count("id", "total")[0]->total;
        $projects["cancelled"] = Database::table('projects')->where('company', $user->company)->where('status', "Cancelled")->count("id", "total")[0]->total;
        return $projects;
    }

    public function tasks($user) {
        $tasks = array();
        $tasks["progress"] = Database::table('tasks')->where('company', $user->company)->where('status', "In Progress")->count("id", "total")[0]->total;
        $tasks["complete"] = Database::table('tasks')->where('company', $user->company)->where('status', "Completed")->count("id", "total")[0]->total;
        $tasks["cancelled"] = Database::table('tasks')->where('company', $user->company)->where('status', "Cancelled")->count("id", "total")[0]->total;
        return $tasks;
    }

    /** Income for the last 12 months. */
    public function twelve($user) {
        $now = new \DateTime("11 months ago");
        $interval = new \DateInterval('P1M');
        $period = new \DatePeriod($now, $interval, 11);
        $twelve = array("amount" => array(), "label" => array());

        foreach ($period as $theMonth) {
            $month = $theMonth->format('m');
            $year = $theMonth->format('Y');
            $amount = Database::table('projectpayments')
                ->where('MONTH(`payment_date`)', $month)
                ->where('YEAR(`payment_date`)', $year)
                ->where("company", $user->company)
                ->sum("amount", "total")[0]->total;
            $twelve['amount'][] = round($amount, 2);
            $twelve['label'][] = $theMonth->format('M');
        }

        return $twelve;
    }

    /** Dashboard KPIs, calculated only from the existing tables. */
    public function widgets($user) {
        $widgets = array();

        $total = Database::table('invoices')->where('company', $user->company)->sum("total", "total")[0]->total;
        $paid = Database::table('invoices')->where('company', $user->company)->sum("amount_paid", "total")[0]->total;
        $widgets["unpaidinvoices"] = max(0, $total - $paid);

        $overdueTotal = Database::table('invoices')->where('company', $user->company)->where('due_date', "<", date("Y-m-d"))->sum("total", "total")[0]->total;
        $overduePaid = Database::table('invoices')->where('company', $user->company)->where('due_date', "<", date("Y-m-d"))->sum("amount_paid", "total")[0]->total;
        $widgets["overdueinvoices"] = max(0, $overdueTotal - $overduePaid);

        $widgets["activeprojects"] = Database::table('projects')->where('company', $user->company)->where('status', "In Progress")->count("id", "total")[0]->total;
        $widgets["completedprojects"] = Database::table('projects')->where('company', $user->company)->where('status', "Completed")->count("id", "total")[0]->total;
        $widgets["bookedinprojects"] = Database::table('projects')->where('company', $user->company)->where('status', "Booked In")->count("id", "total")[0]->total;

        $widgets["pendingtasks"] = Database::table('tasks')->where('company', $user->company)->where('status', "In Progress")->count("id", "total")[0]->total;
        $widgets["completedtasks"] = Database::table('tasks')->where('company', $user->company)->where('status', "Completed")->count("id", "total")[0]->total;

        $widgets["todayrevenue"] = Database::table('projectpayments')->where('company', $user->company)->where('payment_date', date('Y-m-d'))->sum("amount", "total")[0]->total;
        $widgets["incomethismonth"] = Database::table('projectpayments')->where('company', $user->company)->where('MONTH(`payment_date`)', date("m"))->where('YEAR(`payment_date`)', date("Y"))->sum("amount", "total")[0]->total;
        $widgets["paymentsthismonth"] = Database::table('projectpayments')->where('company', $user->company)->where('MONTH(`payment_date`)', date("m"))->where('YEAR(`payment_date`)', date("Y"))->count("id", "total")[0]->total;
        $widgets["incomethisyear"] = Database::table('projectpayments')->where('company', $user->company)->where('YEAR(`payment_date`)', date("Y"))->sum("amount", "total")[0]->total;

        $expenses = Database::table('expenses')->where('company', $user->company)->where('YEAR(`expense_date`)', date("Y"))->sum("amount", "total")[0]->total;
        $taskcost = Database::table('tasks')->where('company', $user->company)->where('YEAR(`created_at`)', date("Y"))->sum("cost", "total")[0]->total;
        $invoiced = Database::table('invoices')->where('company', $user->company)->where('YEAR(`invoice_date`)', date("Y"))->sum("total", "total")[0]->total;
        $widgets["expensesyear"] = $expenses + $taskcost;
        $widgets["invoicedyear"] = $invoiced;
        $widgets["profits"] = $invoiced - ($taskcost + $expenses);

        $widgets["totalclients"] = Database::table('clients')->where('company', $user->company)->count("id", "total")[0]->total;
        $widgets["totalstaff"] = Database::table('users')->where('company', $user->company)->count("id", "total")[0]->total;
        $widgets["inventoryitems"] = Database::table('inventory')->where('company', $user->company)->count("id", "total")[0]->total;

        // Field-to-field comparison is done as read-only SQL. No schema changes.
        $company = (int) $user->company;
        $lowStock = Database::table('inventory')->fetch("SELECT COUNT(id) AS total FROM `inventory` WHERE `company` = {$company} AND `restock_quantity` > 0 AND `quantity` <= `restock_quantity`");
        $widgets["lowstock"] = !empty($lowStock) ? (int) $lowStock[0]->total : 0;

        return $widgets;
    }

    /** Recent operational records for the command-center dashboard. */
    public function recent($user) {
        $company = (int) $user->company;
        $recent = array(
            'projects' => array(),
            'invoices' => array(),
            'stock' => array(),
            'services' => array()
        );

        $recent['projects'] = Database::table('projects')->fetch(
            "SELECT p.id, p.make, p.model, p.registration_number, p.status, p.date_in, p.created_at, c.fullname AS client_name
             FROM `projects` p
             LEFT JOIN `clients` c ON c.id = p.client
             WHERE p.company = {$company}
             ORDER BY p.id DESC LIMIT 7"
        );

        $recent['invoices'] = Database::table('invoices')->fetch(
            "SELECT i.id, i.total, i.amount_paid, i.status, i.invoice_date, p.registration_number, c.fullname AS client_name
             FROM `invoices` i
             LEFT JOIN `projects` p ON p.id = i.project
             LEFT JOIN `clients` c ON c.id = i.client
             WHERE i.company = {$company}
             ORDER BY i.id DESC LIMIT 6"
        );

        $recent['stock'] = Database::table('inventory')->fetch(
            "SELECT id, name, quantity, quantity_unit, restock_quantity, item_code
             FROM `inventory`
             WHERE company = {$company} AND restock_quantity > 0 AND quantity <= restock_quantity
             ORDER BY quantity ASC LIMIT 6"
        );

        $recent['services'] = Database::table('tasks')->fetch(
            "SELECT title, COUNT(id) AS total
             FROM `tasks`
             WHERE company = {$company} AND title IS NOT NULL AND title <> ''
             GROUP BY title
             ORDER BY total DESC LIMIT 5"
        );

        return $recent;
    }
}
