<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem\CompoundingMixing;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Helpers\ResponseHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\UserInfoHelper;
use App\Helpers\FileHelper;
use DateTime;


class ApiController extends Controller
{
    public const GET_PERMISSION_ALIAS = null;

    public const POST_PERMISSION_ALIAS = null;

    public const PUT_PERMISSION_ALIAS = null;

    public const DELETE_PERMISSION_ALIAS = null;

    public const FILE_UPLOAD_PERMISSION_ALIAS = null;



    protected $response;
    protected $validation;
    protected $db;
    protected $file;
    protected $user_info;
    protected $account;
    public function __construct(Request $request)
    {
        $this->file = new FileHelper();
        $this->response = new ResponseHelper($request);
        $this->validation = new ValidationHelper($request);
        $this->user_info = new UserInfoHelper();
        /**
         * 
         *  Rename system_database_connection based on preferred database on database.php
         * 
        */
        $this->db = DB::connection("production_database_connection");
        $this->account = DB::connection("accounts_connection");
    }

     /**
     * 
     * modify accepted parameters
     * 
     * */
    protected $accepted_parameters = [
        "id",
        "equipment_name",
        "category_id",
        "manufacturer",
        "model_number",
        "serial_number",
        "purchase_date",
        "purchase_price",
        "supplier",
        "warranty_expiration",
        "condition",
        "location",
        "assigned_to",
        "maintenance_schedule",
        "status",
        "description",
        "equipment_image",
        "safety_manual",
        "operation_manual",
        "maintenance_manual",
        "search_keyword",
        "activity_logs"
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
       "equipment_number",
       "equipment_name",
       "category_id",
       "manufacturer",
       "model_number",
       "serial_number",
       "purchase_date",
       "location",
       "assigned_to",
       "status",
       "description"
    ];

    /**
     * 
     * modify response column
     * 
     * */
    protected $response_column = [
       "id",
        "equipment_name",
        "category_id",
        "manufacturer",
        "model_number",
        "serial_number",
        "purchase_date",
        "purchase_price",
        "supplier",
        "warranty_expiration",
        "condition",
        "location",
        "assigned_to",
        "maintenance_schedule",
        "status",
        "description",
        "equipment_image",
        "equipment_image_geenrated_filename",
        "safety_manual",
        "safety_manual_generated_filename",
        "operation_manual",
        "operation_manual_generated_filename",
        "maintenance_manual",
        "maintenance_manual_generated_filename",
        "activity_logs"
    ];

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'equipments';
    protected $table_category = 'category_lists';
    protected $table_activity_logs = 'equipment_activity_logs';
    protected $table_user_info = 'user_information';




    public function get(Request $request, $id = null){

        try{
            if($id){
                //if id has value
            }
            $search = $request->query('search_keyword');

            if($search){
                // if search has value
                //put some query
            }
            /**
             * 
             *  if there is no id and search, the query below will execute
             * 
             *  edit query below based on module requirements
             * 
             */
            $query_result = $this->db->table($this->table)->get();
            return $this->response->buildApiResponse($query_result,$this->response_column);

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
            
            
       
    }

    public function post(Request $request){
        
        $payload = $request->all();
        if(!empty($payload)){
            foreach ($payload as $field => $value) {
                if (!in_array($field, $this->accepted_parameters)) {
                    return $this->response->invalidParameterResponse();
                }
            }
        }

         /**
         * 
         * start with other validations here
         * 
         * */

        try{

             /**
             * 
             * 
             * insert your code here
             * 
             * can remove this comment after
             * 
             * 
             * */

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }

    }

    public function put(Request $request, $id){


        $payload = $request->all();
        if(!empty($payload)){
            foreach ($payload as $field => $value) {
                if (!in_array($field, $this->accepted_parameters)) {
                    return $this->response->invalidParameterResponse();
                }
            }
        }

         /**
         * 
         * start with other validations here
         * 
         * */

        try{
            /**
             * 
             * 
             * insert your code here
             * 
             * can remove this comment after
             * 
             * 
             * */
        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function delete($id){

        //check if the id is numeric and has value
        if (empty($id) && !is_numeric($id)) {
            return $this->response->errorResponse("Invalid Request");
        }

        $payload = $payload->all();
        if(!isset($payload['id']) || empty($payload['id']) || !is_numeric($payload['id'])){
            //if id is not set in $request, empty or non numeric
            return $this->response->invalidParameterResponse();
        }
        if($payload['id'] != $id){
            //if ids doesnt match
            return $this->response->errorResponse("ID doesn't match!");
        }

        try{

             /**
             * 
             * 
             * insert your code here
             * 
             * can remove this comment after
             * 
             * 
             * */

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function upload(Request $request, $id){

         /**
         * 
         * start with other validations here
         * 
         * */


        try{

             /**
             * 
             * 
             * insert your code here
             * 
             * can remove this comment after
             * 
             * 
             * */

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }
}