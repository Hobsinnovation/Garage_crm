<?php
namespace Simcify\Controllers;

$tcpdfPath = str_replace("Controllers", "TCPDF/", dirname(__FILE__));

require_once $tcpdfPath.'tcpdf.php';

use TCPDF;
use Simcify\Database;
use Simcify\Asilify;
use Simcify\Auth;
use Simcify\Mail;
use Simcify\File;

class Jobcards {
    
    /**
     * Create job card form view
     * 
     * @return \Pecee\Http\Response
     */
    public function createform() {
        
        $user   = Auth::user();
        if (!empty(input("clientid"))) {
            $client = Database::table('clients')->where('company', $user->company)->where('id', input("clientid"))->first();
            $projects = Database::table('projects')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
        }else{
            $insurance = Database::table('insurance')->where('company', $user->company)->where('id', input("insuranceid"))->first();
            $projects = Database::table('projects')->where('company', $user->company)->where('insurance', $insurance->id)->orderBy("id", false)->get();
        }
        
        
        
        return view('modals/create-jobcard', compact("projects","user"));
        
    }
    
    
    /**
     * Create a task  
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        
        $project = Database::table('projects')->where('company', $user->company)->where('id', input("project"))->first();
        
        $data = array(
            "client" => $project->client,
            "company" => $user->company,
            "project" => escape(input('project')),
            "approved" => escape(input('approved'))
        );

        if (!empty($_POST["body_report"])) {
            $data["body_report"] = Asilify::array2json($_POST["body_report"]);
        }
        
        if (!empty($_POST["mechanical_report"])) {
            $data["mechanical_report"] = Asilify::array2json($_POST["mechanical_report"]);
        }
        
        if (!empty($_POST["electrical_report"])) {
            $data["electrical_report"] = Asilify::array2json($_POST["electrical_report"]);
        }

        if (!empty($project->insurance)) {
            $data["insurance"] = $project->insurance;
            unset($data["client"]);
        }

        if (!empty(input("jobcardid"))) {
            $data["assessment"] = escape(input('jobcardid'));
        }

        Database::table('jobcards')->insert($data);
        return response()->json(responder("success", "Alright!", "Job card successfully created.", "redirect('" . url('Projects@details', array(
            'projectid' => input('project')
        )) . "?view=jobcards')"));
        
    }
    
    
    /**
     * Job card update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', input("jobcardid"))->first();

        $approved = Database::table('jobcards')->where('company', $user->company)->where('assessment', input("jobcardid"))->first();
        
        if (input("action") == "approved") {
            return view('modals/approved-jobcard', compact("jobcard","approved"));
        }else{
            return view('modals/update-jobcard', compact("jobcard"));
        }
        
        
    }
    
    /**
     * Update Job card
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        
        $data = array(
            "approved" => escape(input('approved'))
        );

        if (!empty($_POST["body_report"])) {
            $data["body_report"] = Asilify::array2json($_POST["body_report"]);
        }
        
        if (!empty($_POST["mechanical_report"])) {
            $data["mechanical_report"] = Asilify::array2json($_POST["mechanical_report"]);
        }
        
        if (!empty($_POST["electrical_report"])) {
            $data["electrical_report"] = Asilify::array2json($_POST["electrical_report"]);
        }
        
        Database::table('jobcards')->where('id', input('jobcardid'))->where('company', $user->company)->update($data);
        return response()->json(responder("success", "Alright!", "Job card successfully updated.", "reload()"));
        
    }
    
    /**
     * Delete Job card
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', input('jobcardid'))->first();
        Database::table('jobcards')->where('id', input('jobcardid'))->where('company', $user->company)->delete();
        
        return response()->json(responder("success", "Alright!", "Job card successfully deleted.", "redirect('" . url('Projects@details', array(
            'projectid' => $jobcard->project
        )) . "?view=jobcards')"));
        
    }

    /**
     * View Job card
     * 
     * @return \Pecee\Http\Response
     */
    public function view($jobcardid) {

        $user   = Auth::user();
        $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', $jobcardid)->first();
        if (empty($jobcard)) {
            return view('errors/404');
        }

        $title = "Job card #".$jobcard->id;

        return view('view-jobcard', compact("title", "user","jobcard"));
        
    }
    
    /**
     * Send via email
     * 
     * @return Json
     */
    public function send() {
        
        $user   = Auth::user();
        $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', input("itemid"))->first();
        
        if (empty($jobcard)) {
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }
        
        $project = Database::table('projects')->where('company', $user->company)->where('id', $jobcard->project)->first();
        $client = Database::table('clients')->where('company', $user->company)->where('id', $project->client)->first();
        if(!empty($project->insurance)){
            $project->insurance = Database::table('insurance')->where('id', $project->insurance)->first();
        }


        $document = $this->generate($user, $jobcard, $project, $client, true);

        $send = Mail::send(
            input("email"),
            input("subject"),
            array(
                "message" => nl2br(input("message"))
            ),
            "basic",
            null,
            array("Job Card #".$jobcard->id.".pdf" => config("app.storage")."tmp/".$document)
        );

        File::delete($document, "tmp");

        if ($send) {
            return response()->json(responder("success", "Alright!", "Email successfully sent.", "reload()"));
        }else{
            return response()->json(responder("error", "Hmmm!", "Email could not be sent, please try again."));
        }

        die();
        
    }
    
    /**
     * Generate PDF
     * 
     * @return application/pdf
     */
    public function render($jobcardid) {
        if (ob_get_level() === 0) { ob_start(); }
        @ini_set('display_errors', '0');
        try {
            $user = Auth::user();
            if (empty($user)) { throw new \RuntimeException('Authentication session is not available to the PDF request.'); }
            $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', $jobcardid)->first();
            if (empty($jobcard)) { throw new \RuntimeException('Job Card #' . $jobcardid . ' was not found.'); }

            $project = Database::table('projects')->where('company', $user->company)->where('id', $jobcard->project)->first();
            if (empty($project)) { throw new \RuntimeException('Vehicle record linked to Job Card #' . $jobcardid . ' was not found.'); }
            $clientId = !empty($project->client) ? $project->client : (!empty($jobcard->client) ? $jobcard->client : null);
            $client = !empty($clientId) ? Database::table('clients')->where('company', $user->company)->where('id', $clientId)->first() : null;
            if (empty($client)) {
                $client = (object) array('fullname'=>'Archived / Unknown Client','phonenumber'=>'','email'=>'','address'=>'');
            }
            if (!empty($project->insurance)) {
                $project->insurance = Database::table('insurance')->where('company', $user->company)->where('id', $project->insurance)->first();
            } else {
                $project->insurance = null;
            }
            $this->generate($user, $jobcard, $project, $client);
        } catch (\Throwable $error) {
            pdf_failure_response('Job Card #' . $jobcardid, $error);
        }
    }


    /**
     * Generate premium Union Star workshop job card PDF.
     */
    public function generate($user, $jobcard, $project, $client, $save = false) {

        $outputName = uniqid("unionstar_jobcard_").".pdf";
        $outputPath = config("app.storage")."/tmp/".$outputName;

        $pdf = new PDF('P', 'px', 'A4', true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->SetCreator('Union Star Auto Garage CRM');
        $pdf->SetAuthor($user->parent->name);
        $pdf->SetTitle('Job Card #'.$jobcard->id);
        $pdf->SetPrintHeader(false);
        $pdf->SetMargins(30, 24, 30);
        $pdf->SetAutoPageBreak(true, 54);
        $pdf->AddPage();
        $pdf->company = $user->parent;

        $navy = array(7,22,47); $orange = array(255,107,26);
        $text = array(31,42,61); $muted = array(112,128,151);
        $border = array(226,232,241); $soft = array(248,250,253);

        if (!empty($project->work_requested) && is_string($project->work_requested)) {
            $decoded = json_decode($project->work_requested);
            $project->work_requested = is_array($decoded) ? $decoded : array();
        } elseif (empty($project->work_requested)) {
            $project->work_requested = array();
        }

        $make = trim((string)carmake($project->make));
        $model = trim((string)carmodel($project->model));
        $vehicleName = trim($make.' '.$model);
        if ($vehicleName === '') { $vehicleName = 'Vehicle record'; }
        $registration = !empty($project->registration_number) ? $project->registration_number : '--';
        $vin = !empty($project->vin) ? $project->vin : '--';
        $mileage = !empty($project->milleage) ? $project->milleage.' '.(!empty($project->milleage_unit) ? $project->milleage_unit : '') : '--';

        $logo = document_asset('assets/images/unionstar-pdf-logo.jpg');
        if (is_file($logo)) { $pdf->Image($logo,30,23,62); }
        $pdf->SetXY(102,24); $pdf->SetFont('','B',16); $pdf->SetTextColor(7,22,47);
        $pdf->Cell(260,20,strtoupper($user->parent->name),0,1,'L');
        $pdf->SetX(102); $pdf->SetFont('','',8.5); $pdf->SetTextColor(112,128,151);
        $bits=array(); if(!empty($user->parent->phone)){$bits[]=$user->parent->phone;} if(!empty($user->parent->email)){$bits[]=$user->parent->email;}
        $pdf->Cell(280,15,implode('  |  ',$bits),0,1,'L');
        $pdf->SetX(102); $pdf->SetFont('','',8); $pdf->Cell(280,14,(string)$user->parent->address,0,0,'L');

        $pdf->SetXY(390,20); $pdf->SetFont('','B',20); $pdf->SetTextColor(7,22,47);
        $pdf->Cell(165,25,'JOB CARD',0,1,'R');
        $pdf->SetX(390); $pdf->SetFont('','B',10); $pdf->SetTextColor(255,107,26);
        $pdf->Cell(165,15,'JC-'.str_pad($jobcard->id,6,'0',STR_PAD_LEFT),0,1,'R');
        $pdf->SetX(390); $pdf->SetFont('','',8.5); $pdf->SetTextColor(112,128,151);
        $pdf->Cell(165,14,'Created: '.date('d M Y',strtotime($jobcard->created_at)),0,0,'R');

        $label = (!empty($jobcard->approved) && $jobcard->approved === 'Yes') ? 'APPROVED' : 'IN PROGRESS';
        $bg = ($label==='APPROVED') ? array(232,248,240) : array(255,242,232);
        $fg = ($label==='APPROVED') ? array(16,139,91) : array(221,104,24);
        $pdf->SetFillColor($bg[0],$bg[1],$bg[2]); $pdf->RoundedRect(465,77,90,19,5,'1111','F');
        $pdf->SetXY(465,81); $pdf->SetFont('','B',8); $pdf->SetTextColor($fg[0],$fg[1],$fg[2]); $pdf->Cell(90,10,$label,0,0,'C');

        $pdf->SetDrawColor(7,22,47); $pdf->SetLineWidth(1.3); $pdf->Line(30,104,555,104);
        $pdf->SetDrawColor(255,107,26); $pdf->SetLineWidth(3); $pdf->Line(30,104,145,104);

        $cardY=122; $cardH=112;
        $pdf->SetFillColor(248,250,253); $pdf->SetDrawColor(226,232,241);
        $pdf->RoundedRect(30,$cardY,252,$cardH,7,'1111','DF'); $pdf->RoundedRect(296,$cardY,259,$cardH,7,'1111','DF');
        $pdf->SetXY(44,$cardY+12); $pdf->SetFont('','B',8.5); $pdf->SetTextColor(7,22,47); $pdf->Cell(220,14,'CUSTOMER',0,1,'L');
        $pdf->SetX(44); $pdf->SetFont('','B',11); $pdf->SetTextColor(31,42,61); $pdf->Cell(220,17,(string)$client->fullname,0,1,'L');
        $pdf->SetX(44); $pdf->SetFont('','',8.5); $pdf->SetTextColor(112,128,151);
        if(!empty($client->phonenumber)){ $pdf->Cell(220,14,(string)$client->phonenumber,0,1,'L'); $pdf->SetX(44); }
        if(!empty($client->email)){ $pdf->Cell(220,14,(string)$client->email,0,1,'L'); $pdf->SetX(44); }
        $pdf->MultiCell(220,22,(string)$client->address,0,'L',false,1);

        $pdf->SetXY(310,$cardY+12); $pdf->SetFont('','B',8.5); $pdf->SetTextColor(7,22,47); $pdf->Cell(230,14,'VEHICLE INFORMATION',0,1,'L');
        foreach(array(array('Vehicle',$vehicleName),array('Reg. No.',$registration),array('VIN',$vin),array('Mileage',$mileage)) as $row){
            $pdf->SetX(310); $pdf->SetFont('','B',8); $pdf->SetTextColor(112,128,151); $pdf->Cell(58,16,$row[0],0,0,'L');
            $pdf->SetFont('','',8.5); $pdf->SetTextColor(31,42,61); $pdf->Cell(168,16,(string)$row[1],0,1,'L');
        }

        $pdf->SetY(254);
        $section = function($title,$items) use ($pdf) {
            if ($pdf->GetY() > 700) { $pdf->AddPage(); $pdf->SetY(42); }
            $pdf->SetFillColor(7,22,47); $pdf->SetTextColor(255,255,255); $pdf->SetFont('','B',9);
            $pdf->Cell(525,22,$title,0,1,'L',true);
            $pdf->SetTextColor(31,42,61); $pdf->SetFont('','',8.5);
            if (empty($items)) {
                $pdf->SetFillColor(248,250,253); $pdf->Cell(525,28,'No entries recorded.',0,1,'L',true);
                $pdf->Ln(10); return;
            }
            $html='<table cellpadding="6" cellspacing="0" width="100%">';
            foreach($items as $i=>$item){
                if (is_object($item)) { $item = json_encode($item); }
                $bg=(($i%2)===0)?'#FFFFFF':'#F9FBFD';
                $html.='<tr bgcolor="'.$bg.'"><td width="7%" align="center"><b>'.($i+1).'</b></td><td width="93%">'.htmlspecialchars((string)$item,ENT_QUOTES,'UTF-8').'</td></tr>';
            }
            $html.='</table>';
            $pdf->writeHTML($html,true,false,true,false,''); $pdf->Ln(10);
        };

        $section('WORK REQUESTED / CUSTOMER INSTRUCTIONS', is_array($project->work_requested) ? $project->work_requested : array());
        $body = !empty($jobcard->body_report) ? json_decode($jobcard->body_report) : array(); if(!is_array($body)){$body=array();}
        $mechanical = !empty($jobcard->mechanical_report) ? json_decode($jobcard->mechanical_report) : array(); if(!is_array($mechanical)){$mechanical=array();}
        $electrical = !empty($jobcard->electrical_report) ? json_decode($jobcard->electrical_report) : array(); if(!is_array($electrical)){$electrical=array();}
        $section('BODY REPORT', $body);
        $section('MECHANICAL REPORT', $mechanical);
        $section('ELECTRICAL REPORT', $electrical);

        if ($pdf->GetY() > 700) { $pdf->AddPage(); $pdf->SetY(54); }
        $sigY=$pdf->GetY()+18;
        $pdf->SetDrawColor(226,232,241); $pdf->Line(30,$sigY,210,$sigY); $pdf->Line(375,$sigY,555,$sigY);
        $pdf->SetXY(30,$sigY+6); $pdf->SetFont('','',8); $pdf->SetTextColor(112,128,151); $pdf->Cell(180,14,'Customer Signature',0,0,'L');
        $pdf->SetXY(375,$sigY+6); $pdf->Cell(180,14,'Service Advisor / Authorized Signature',0,0,'R');

        if ($save) { $pdf->Output($outputPath,'F'); return $outputName; }
        pdf_inline_response($pdf,'Job-Card-'.$jobcard->id.'.pdf');
    }

    
}


class PDF extends TCPDF {

    public $company;

    public function Footer() {
        $this->SetY(-34);
        $this->SetDrawColor(7, 22, 47);
        $this->SetLineWidth(1.2);
        $this->Line(30, $this->GetY(), 555, $this->GetY());
        $this->SetDrawColor(255, 107, 26);
        $this->SetLineWidth(3);
        $this->Line(30, $this->GetY(), 125, $this->GetY());
        $this->SetY(-25);
        $this->SetFont('', '', 7.5);
        $this->SetTextColor(112, 128, 151);
        $name = (!empty($this->company) && !empty($this->company->name)) ? $this->company->name : 'Union Star Auto Garage';
        $this->Cell(300, 13, $name . '  |  Drive with confidence', 0, 0, 'L');
        $this->Cell(225, 13, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}
