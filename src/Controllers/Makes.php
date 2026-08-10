<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Makes {
    
    /**
     * Render makes page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        
        $title = 'Vehicle Makes';
        $user  = Auth::user();
        if ($user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
            return view('errors/404');
        }
        
        $makes = Database::table('makes')->where('company',"IS", "NULL")->orWhere('company', $user->company)->orderBy("id", false)->get();
        foreach ($makes as $key => $make) {
            $make->models = Database::table('models')->where('makeid', $make->id)->count("id", "total")[0]->total;
        }
        
        return view("makes", compact("user", "title", "makes"));
        
    }
    
    /**
     * Create make 
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name'))
        );

        if ($user->role != "Admin") {
            $data["company"] = $user->company;
        }

        if (!empty(input("status"))) {
            $data["status"] = escape(input('status'));
        }

        Database::table('makes')->insert($data);
        
        return response()->json(responder("success", "Alright!", "Make successfully added.", "reload()"));
        
    }
    
    
    /**
     * make update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        if ($user->role == "Admin") {
            $make = Database::table('makes')->where('id', input("makeid"))->first();
        }else{
            $make = Database::table('makes')->where('company', $user->company)->where('id', input("makeid"))->first();
        }
        
        return view('modals/update-make', compact("make"));
        
    }
    
    /**
     * Update make
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name')),
        );
        
        if ($user->role == "Admin") {
            Database::table('makes')->where('id', input('makeid'))->update($data);
        }else{
            Database::table('makes')->where('id', input('makeid'))->where('company', $user->company)->update($data);
        }
        
        return response()->json(responder("success", "Alright!", "Make successfully updated.", "reload()"));
        
    }
    
    /**
     * Delete make
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $project = Database::table('projects')->where('make', input("makeid"))->first();
        if (!empty($project)) {
            return response()->json(responder("warning", "Hmmm!", "This make can not be deleted because it is linked to a project"));
        }

        if ($user->role == "Admin") {
            $make = Database::table('makes')->where('id', input("makeid"))->delete();
        }else{
            $make = Database::table('makes')->where('company', $user->company)->where('id', input("makeid"))->delete();
        }
        
        return response()->json(responder("success", "Alright!", "Make successfully deleted.", "reload()"));
        
    }
    
}