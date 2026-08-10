<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Expenses {
    
    /**
     * Render unpaid parts page
     * 
     * @return \Pecee\Http\Response
     */
    public function unpaid() {
        
        $title = 'Unpaid Parts';
        $user  = Auth::user();
        
        $expenses = Database::table('expenses')->where('company', $user->company)->where('paid', "No")->orderBy("id", false)->get();
        foreach ($expenses as $key => $expense) {
            $expense->project = Database::table('projects')->where('id', $expense->project)->first();
            if (!empty($expense->supplier)) {
                $expense->supplier = Database::table('suppliers')->where('id', $expense->supplier)->first();
            }
        }
        
        return view("parts-unpaid", compact("user", "title", "expenses"));
        
    }
    
    /**
     * Render expected parts page
     * 
     * @return \Pecee\Http\Response
     */
    public function expected() {
        
        $title = 'Expected Parts';
        $user  = Auth::user();
        
        $expenses = Database::table('expenses')->where('company', $user->company)->where('status',"!=","Delivered")->orderBy("id", false)->get();
        foreach ($expenses as $key => $expense) {
            $expense->project = Database::table('projects')->where('id', $expense->project)->first();
            if (!empty($expense->supplier)) {
                $expense->supplier = Database::table('suppliers')->where('id', $expense->supplier)->first();
            }
        }
        
        return view("parts-expected", compact("user", "title", "expenses"));
        
    }
    
    /**
     * Add an Expense
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();

        if (input("source") == "Suppliers") {

            $data = array(
                "company" => $user->company,
                "project" => escape(input('project')),
                "expense" => escape(input('expense')),
                "amount" => escape(input('amount')),
                "type" => escape(input('type')),
                "status" => escape(input('status')),
                "quantity" => escape(input('quantity')),
                "units" => escape(input('quantity_unit')),
                "expense_date" => escape(input('expense_date'))
            );

            if (!empty(input("expected_delivery_date"))) {
                $data["expected_delivery_date"] = escape(input('expected_delivery_date'));
            }

            if (!empty(input("expected_delivery_time"))) {
                $data["expected_delivery_time"] = escape(input('expected_delivery_time'));
            }

            if (!empty(input("payment_due"))) {
                $data["payment_due"] = escape(input('payment_due'));
            }

            if (!empty(input("supplier"))) {
                if (input("supplier") == "create") {
                    Database::table('suppliers')->insert(array(
                        "company" => $user->company,
                        "name" => escape(input('suppliername')),
                        "phonenumber" => escape(input('phonenumber'))
                    ));
                    $supplierId = Database::table('suppliers')->insertId();
                    if (empty($supplierId)) {
                        return response()->json(responder("error", "Hmmm!", "Something went wrong while creating new supplier, please try again."));
                    }
                    $data["supplier"] = $supplierId;
                }else{
                    $data["supplier"] = escape(input('supplier'));
                }
            }

            if (!empty(input("paid"))) {
                $data["paid"] = "Yes";
            }else{
                $data["paid"] = "No";
            }
            
            Database::table('expenses')->insert($data);
            $expenseid = Database::table('expenses')->insertId();

        }else{

            if (input("consumed") < 0) {
                return response()->json(responder("error", "Hmmm!", "Units Consumed can't be less than 0."));
            }

            $inventory = Database::table('inventory')->where('company', $user->company)->where('id', input("inventory"))->first();

            $data = array(
                "company" => $user->company,
                "project" => escape(input('project')),
                "inventory" => escape(input('inventory')),
                "expense" => $inventory->name,
                "amount" => escape(input('consumed') * $inventory->unit_cost),
                "quantity" => escape(input('consumed')),
                "units" => $inventory->quantity_unit,
                "expense_date" => escape(input('expensedate'))
            );
            
            Database::table('expenses')->insert($data);
            $expenseid = Database::table('expenses')->insertId();

            Database::table('inventory')->where('company', $user->company)->where('id', input("inventory"))->update(array(
                "quantity" => round(($inventory->quantity - input('consumed')), 2)
            ));

            // consumption log

            $data = array(
                "company" => $user->company,
                "project" => escape(input('project')),
                "item" => escape(input('inventory')),
                "issued_by" => escape($user->fname." ".$user->lname),
                "consumed" => escape(input('consumed')),
                "consumed_value" => escape(input('consumed') * $inventory->unit_cost)
            );

            Database::table('inventorylog')->insert($data);

        }

        if (input("source") == "Suppliers" && input("type") == "Part" && $user->parent->parts_to_inventory == "Enabled") {
            $this->addToInventory($expenseid);
        }
        
        return response()->json(responder("success", "Alright!", "Expense successfully added.", "redirect('" . url('Projects@details', array(
            'projectid' => input('project')
        )) . "?view=expenses')"));
        
    }
    
    /**
     * Add an Expense
     * 
     * @return Json
     */
    public function addbulk() {
        
        $user = Auth::user();

        if (empty($_POST["indexing"])) {
            return response()->json(responder("error", "Hmmm!", "No exepense or part added."));
        }

        foreach ($_POST["indexing"] as $key => $index) {

            $expenseid = NULL;

            if (input("source".$index) == "Suppliers") {

                $data = array(
                    "company" => $user->company,
                    "project" => escape(input('project')),
                    "expense" => escape(input('expense'.$index)),
                    "amount" => escape(input('amount'.$index)),
                    "type" => escape(input('type'.$index)),
                    "status" => escape(input('status'.$index)),
                    "quantity" => escape(input('quantity'.$index)),
                    "units" => escape(input('quantity_unit'.$index)),
                    "expense_date" => escape(input('expense_date'.$index))
                );

                if (!empty(input("expected_delivery_date".$index))) {
                    $data["expected_delivery_date"] = escape(input('expected_delivery_date'.$index));
                }

                if (!empty(input("expected_delivery_time".$index))) {
                    $data["expected_delivery_time"] = escape(input('expected_delivery_time'.$index));
                }

                if (!empty(input("payment_due".$index))) {
                    $data["payment_due"] = escape(input('payment_due'.$index));
                }

                if (!empty(input("supplier".$index))) {
                    if (input("supplier".$index) == "create") {
                        Database::table('suppliers')->insert(array(
                            "company" => $user->company,
                            "name" => escape(input('suppliername'.$index)),
                            "phonenumber" => escape(input('phonenumber'.$index))
                        ));
                        $supplierId = Database::table('suppliers')->insertId();
                        if (!empty($supplierId)) {
                            $data["supplier"] = $supplierId;
                        }
                    }else{
                        $data["supplier"] = escape(input('supplier'.$index));
                    }
                }

                if (!empty(input("paid".$index))) {
                    $data["paid"] = "Yes";
                }else{
                    $data["paid"] = "No";
                }

                Database::table('expenses')->insert($data);
                $expenseid = Database::table('expenses')->insertId();

            }else{

                if (input("consumed".$index) < 0) {
                    return response()->json(responder("error", "Hmmm!", "Units Consumed can't be less than 0."));
                }

                $inventory = Database::table('inventory')->where('company', $user->company)->where('id', input("inventory".$index))->first();

                $data = array(
                    "company" => $user->company,
                    "project" => escape(input('project')),
                    "inventory" => escape(input('inventory'.$index)),
                    "expense" => $inventory->name,
                    "amount" => escape(input('consumed'.$index) * $inventory->unit_cost),
                    "quantity" => escape(input('consumed'.$index)),
                    "units" => $inventory->quantity_unit,
                    "expense_date" => escape(input('expensedate'.$index))
                );
                
                Database::table('expenses')->insert($data);
                $expenseid = Database::table('expenses')->insertId();

                Database::table('inventory')->where('company', $user->company)->where('id', input("inventory".$index))->update(array(
                    "quantity" => round(($inventory->quantity - input('consumed'.$index)), 2)
                ));

                // consumption log

                $data = array(
                    "company" => $user->company,
                    "project" => escape(input('project')),
                    "item" => escape(input('inventory'.$index)),
                    "issued_by" => escape($user->fname." ".$user->lname),
                    "consumed" => escape(input('consumed'.$index)),
                    "consumed_value" => escape(input('consumed'.$index) * $inventory->unit_cost)
                );

                Database::table('inventorylog')->insert($data);

            }

            if (input("type".$index) == "Part" && $user->parent->parts_to_inventory == "Enabled") {
                $this->addToInventory($expenseid);
            }

        }
        
        return response()->json(responder("success", "Alright!", "Expense successfully added.", "redirect('" . url('Projects@details', array(
            'projectid' => input('project')
        )) . "?view=expenses')"));
        
    }
    
    /**
     * Add expense part to inventory 
     * 
     * @return Json
     */
    public function addToInventory($expenseid) {

        if (empty($expenseid)) {
            return;
        }

        $expense = Database::table('expenses')->where('id', $expenseid)->first();

        $data = array(
            "company" => $expense->company,
            "name" => $expense->expense,
            "quantity" => $expense->quantity,
            "quantity_unit" => $expense->units,
            "unit_cost" => round(($expense->amount / $expense->quantity), 2),
            "project" => $expense->project,
            "expense" => $expense->id,
            "project_specific" => "Yes",
            "received" => "No"
        );

        if (!empty($expense->supplier)) {
            $data["supplier"] = $expense->supplier;
        }

        Database::table('inventory')->insert($data);
        
        return;
        
    }
    
    /**
     * Add expense part to inventory 
     * 
     * @return Json
     */
    public function updateInventory($expenseid) {

        $expense = Database::table('expenses')->where('id', $expenseid)->first();

        $data = array(
            "name" => $expense->expense,
            "quantity" => $expense->quantity,
            "quantity_unit" => $expense->units,
            "unit_cost" => round(($expense->amount / $expense->quantity), 2)
        );

        if ($expense->supplier) {
            $data["supplier"] = $expense->supplier;
        }

        Database::table('inventory')->where("expense", $expense->id)->update($data);
        
        return;
        
    }
    
    
    /**
     * Import expenses from work requested
     * 
     * @return \Pecee\Http\Response
     */
    public function workrequested() {
        
        $user   = Auth::user();
        $project = Database::table('projects')->where('company', $user->company)->where('id', input("projectid"))->first();
        $suppliers = Database::table('suppliers')->where('company', $user->company)->orderBy("id", false)->get();

        if (!empty($project->work_requested)) {
            $project->work_requested = json_decode($project->work_requested);
        }else{
            $project->work_requested = array();
        }

        $inventory = Database::table('inventory')->where('company', $user->company)->where('project_specific', "No")->orderBy("id", false)->get();
        
        return view('modals/import-expense-workrequested', compact("project","user","suppliers","inventory"));
        
    }
    
    
    /**
     * Import expenses from jobcards
     * 
     * @return \Pecee\Http\Response
     */
    public function jobcards() {
        
        $items = array();
        $user   = Auth::user();
        $suppliers = Database::table('suppliers')->where('company', $user->company)->orderBy("id", false)->get();
        $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', input("jobcardid"))->first();

        if(!empty($jobcard->body_report)){
            $items = array_merge($items, json_decode($jobcard->body_report));
        }

        if(!empty($jobcard->mechanical_report)){
            $items = array_merge($items, json_decode($jobcard->mechanical_report));
        }

        if(!empty($jobcard->electrical_report)){
            $items = array_merge($items, json_decode($jobcard->electrical_report));
        }

        $inventory = Database::table('inventory')->where('company', $user->company)->where('project_specific', "No")->orderBy("id", false)->get();
        
        return view('modals/import-expense-jobcard', compact("items","user","suppliers","jobcard","inventory"));
        
    }
    
    
    /**
     * Expense update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        $expense = Database::table('expenses')->where('company', $user->company)->where('id', input("expenseid"))->first();
        $suppliers = Database::table('suppliers')->where('company', $user->company)->orderBy("id", false)->get();
        
        return view('modals/update-expense', compact("expense","user","suppliers"));
        
    }
    
    /**
     * Update Expense
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        $expense = Database::table('expenses')->where('id', input("expenseid"))->where('company', $user->company)->first();
        
        $data = array(
            "expense" => escape(input('expense')),
            "amount" => escape(input('amount')),
            "type" => escape(input('type')),
            "status" => escape(input('status')),
            "quantity" => escape(input('quantity')),
            "expense_date" => escape(input('expense_date'))
        );

        if (!empty(input("expected_delivery_date"))) {
            $data["expected_delivery_date"] = escape(input('expected_delivery_date'));
        }

        if (!empty(input("expected_delivery_time"))) {
            $data["expected_delivery_time"] = escape(input('expected_delivery_time'));
        }

        if (!empty(input("payment_due"))) {
            $data["payment_due"] = escape(input('payment_due'));
        }

        if (!empty(input("supplier"))) {
            if (input("supplier") == "create") {
                Database::table('suppliers')->insert(array(
                    "company" => $user->company,
                    "name" => escape(input('suppliername')),
                    "phonenumber" => escape(input('phonenumber'))
                ));
                $supplierId = Database::table('suppliers')->insertId();
                if (empty($supplierId)) {
                    return response()->json(responder("error", "Hmmm!", "Something went wrong while creating new supplier, please try again."));
                }
                $data["supplier"] = $supplierId;
            }else{
                $data["supplier"] = escape(input('supplier'));
            }
        }

        if (!empty(input("paid"))) {
            $data["paid"] = "Yes";
        }else{
            $data["paid"] = "No";
        }
        
        Database::table('expenses')->where('id', input('expenseid'))->where('company', $user->company)->update($data);

        if ($user->parent->parts_to_inventory == "Enabled") {
            $inventory = Database::table('inventory')->where('company', $user->company)->where('expense', $expense->id)->first();
            if (!empty($inventory)) {
                $this->updateInventory($expense->id);
            }elseif (input("type") == "Part" && $expense->type == "Service") {
                $this->addToInventory($expense->id);
            }
        }
        return response()->json(responder("success", "Alright!", "Expense successfully updated.", "reload()"));
        
    }
    
    
    /**
     * Delete Expense
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $expense = Database::table('expenses')->where('company', $user->company)->where('id', input("expenseid"))->first();
        if (!empty($expense->inventory)) {
            $inventory = Database::table('inventory')->where('company', $user->company)->where('id', $expense->inventory)->first();
            if (!empty($inventory)) {
                Database::table('inventory')->where('company', $user->company)->where('id', $inventory->id)->update(array(
                    "quantity" => round(($inventory->quantity + $expense->quantity), 2)
                ));
            }
        }

        Database::table('expenses')->where('id', input('expenseid'))->where('company', $user->company)->delete();
        
        return response()->json(responder("success", "Alright!", "Expense successfully deleted.", "reload()"));
        
    }
    
}
