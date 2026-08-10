<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Models {
    
    /**
     * Render models page
     * 
     * @return \Pecee\Http\Response
     */
    public function get($makeid) {
        
        $user  = Auth::user();
        $make = Database::table('makes')->where('id', $makeid)->first();
        if (empty($make)) {
            return view('errors/404');
        }
        $title = $make->name." Models";
        
        if ($user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
            return view('errors/404');
        }
        
        $models = Database::table('models')->where('company',"IS", "NULL")->where("makeid",$make->id)->orWhere('company', $user->company)->where("makeid",$make->id)->orderBy("id", false)->get();
        
        return view("models", compact("user", "title", "models","make"));
        
    }
    
    /**
     * Create model 
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name')),
            "makeid" => escape(input('makeid'))
        );

        if ($user->role != "Admin") {
            $data["company"] = $user->company;
        }

        Database::table('models')->insert($data);
        
        return response()->json(responder("success", "Alright!", "Model successfully added.", "reload()"));
        
    }
    
    
    /**
     * models update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        if ($user->role == "Admin") {
            $model = Database::table('models')->where('id', input("modelid"))->first();
        }else{
            $model = Database::table('models')->where('company', $user->company)->where('id', input("modelid"))->first();
        }
        
        return view('modals/update-model', compact("model"));
        
    }
    
    /**
     * Update model
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name')),
        );
        
        if ($user->role == "Admin") {
            Database::table('models')->where('id', input('modelid'))->update($data);
        }else{
            Database::table('models')->where('id', input('modelid'))->where('company', $user->company)->update($data);
        }
        
        return response()->json(responder("success", "Alright!", "Model successfully updated.", "reload()"));
        
    }
    
    /**
     * Delete model
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $project = Database::table('projects')->where('model', input("modelid"))->first();
        if (!empty($project)) {
            return response()->json(responder("warning", "Hmmm!", "This model can not be deleted because it is linked to a project"));
        }

        if ($user->role == "Admin") {
            $make = Database::table('models')->where('id', input("modelid"))->delete();
        }else{
            $make = Database::table('models')->where('company', $user->company)->where('id', input("modelid"))->delete();
        }
        
        return response()->json(responder("success", "Alright!", "Model successfully deleted.", "reload()"));
        
    }
    
}