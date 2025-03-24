<?php

/**
 *
 * replace the SystemName based on the Folder
 *
 */

namespace App\Http\Controllers\ProductionManagementSystem\ProductionPlanning;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Helpers\ResponseHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\UserInfoHelper;
use App\Helpers\FileHelper;
use DateTime;


class ApiController extends Controller {
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
    public function __construct(Request $request) {
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
        "search_keyword",
        "offset",
        "batch_number",
        "product_id",
        "description",
        "quantity",
        "start_date_and_time",
        "end_date_and_time",
        "customer_name",
        "purchase_order_number",
        "sales_order_number",
        "comments_notes",
        "material_details"
    ];

    /**
     *
     * modify required fields based on accepted parameters
     *
     * */
    protected $required_fields = [
        "batch_number",
        "product_id",
        "quantity",
        "start_date_and_time",
        "end_date_and_time",
        "customer_name",
        "purchase_order_number",
        "sales_order_number"
    ];

    /**
     *
     * modify response column
     *
     * */
    protected $response_column = [
        "id",
        "batch_number",
        "product_id",
        "description",
        "quantity",
        "start_date_and_time",
        "end_date_and_time",
        "customer_name",
        "purchase_order_number",
        "sales_order_number",
        "comments_notes",
        "status",
        "is_archived",
        "material_details",
        "activity_logs"
    ];


    protected $table = 'production_planning';
    protected $table_material_details = 'production_material_details';
    protected $table_activity_logs = 'production_activity_logs';
    protected $table_products = 'products';


    public function get(Request $request, $id = null) {

        try {
            $query_result = [];

            if ($id) {
                if ($id == 0) {
                    return $this->response->errorResponse("The Id Value Cant be zero");
                } else {
                    $query_result = $this->db->table($this->table)->where('id', $id)->first();

                    if (!$query_result) {
                        return $this->response->errorResponse("No Data found With ID");
                    } else {
                        $query_result->material_details = $this->db->table($this->table_material_details)->where('production_plan_id', $query_result->id)->get();
                        $query_result->activity_logs = $this->db->table($this->table_activity_logs)->where('production_plan_id', $query_result->id)->get();
                    }
                }
            } else if ($request->has('offset')) {
                $offset = $request->query('offset', '');
                $limit = $request->query('limit', 1000);

                $query_result = $this->db->table($this->table . " as pp")
                    ->select(
                        'pp.id',
                        'pp.batch_number',
                        'pp.product_id',
                        'p.name',
                        'pp.quantity',
                        'pp.start_date_and_time',
                        'pp.end_date_and_time',
                        'pp.customer_name',
                        'pp.status',
                        'pp.is_archived'
                    )
                    ->leftJoin($this->table_products . ' as p', 'pp.product_id', '=', 'p.id')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();
            } else if ($request->has('search_keyword')) {
                $search = $request->input('search_keyword');
                $query_result = $this->db->table($this->table . " as pp")
                    ->select(
                        'pp.id',
                        'pp.batch_number',
                        'pp.product_id',
                        'p.product_name',
                        'pp.quantity',
                        'pp.start_date_and_time',
                        'pp.end_date_and_time',
                        'pp.customer_name',
                        'pp.status',
                        'pp.is_archived'
                    )
                    ->leftJoin($this->table_products . ' as p', 'p.id', '=', 'pp.product_id')
                    // **Search Condition**
                    ->havingRaw("
                                            p.product_name LIKE ?
                                            OR pp.batch_number LIKE ?
                                         ", ["%$search%", "%$search%"]) // Search keyword applied
                    ->get();
            }

            return $this->response->buildApiResponse($query_result, $this->response_column);
        } catch (QueryException $e) {
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function post(Request $request) {

        $payload = $this->validation->validateRequest($request, $this->accepted_parameters, $this->required_fields);
        //check if the $payload has error validation key
        if (isset($payload['error_validation'])) {
            $this->db->rollback();
            return $this->response->errorResponse($payload['message']);
        }
        try {
            $this->db->beginTransaction();

            $material_details = $payload['material_details'];
            $payload['status'] = 0;
            $payload['is_archived'] = 0;

            unset($payload['material_details']);

            $payload['id'] = $this->db->table($this->table)->insertGetId($payload);

            if (!$payload['id']) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
            } else {

                foreach ($material_details as &$md) {
                    $md['production_plan_id'] = $payload['id'];
                }

                if (!$this->db->table($this->table_material_details)->insert($material_details)) {
                    $this->db->rollbakc();
                    return $this->response->errorResponse("Can't Save Material Details");
                }

                $activity_logs_data = [
                    'production_plan_id' => $payload['id'],
                    'personnel_id' => $this->user_info->getUserId(),
                    'action' => $payload['status'],
                    'date_and_time' => now()
                ];

                $activity_logs_data['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_logs_data);

                if (!$activity_logs_data['id']) {
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Activity Logs");
                } else {

                    $payload['activity_logs'] = $activity_logs_data;

                    $this->db->commit();

                    return $this->response->buildApiResponse($payload, $this->response_column);
                }
            }
        } catch (QueryException $e) {
            $this->db->rollback();
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->response->errorResponse($e);
        }
    }

    public function put(Request $request, $id) {

        $for_archived = ['is_archived', 'id'];
        $for_status = ['status', 'id'];

        $request_keys = array_keys($request->all());
        sort($request_keys);
        sort($for_archived);
        sort($for_status);

        $edit_request = $request->all();

        if ($request_keys === $for_status) {

            if ($id == 0) {
                return $this->response->errorResponse("Id cannot be zero");
            }

            if ($edit_request['id'] != $id) {
                return $this->response->errorResponse("Ids Does not match");
            }


            $this->db->beginTransaction();

            if ($this->db->table($this->table)->where('id', $id)->where($edit_request)->exists()) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update. Data have Similar Status");
            }

            if (!$this->db->table($this->table)->where("id", $id)->update($edit_request)) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }


            $activity_logs = [
                "processing_id" => $id,
                "action" => $edit_request['status'],
                "user_id" => $this->user_info->getUserId(),
                "date_time" => now()
            ];

            $activity_logs['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_logs);

            if (empty($activity_logs['id'])) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
            }

            $activity_logs['personnel'] = trim(
                $this->user_info->getUserInformation()['first_name'] . ' ' .
                    ($this->user_info->getUserInformation()['middle_name'] ?? '') . ' ' .
                    $this->user_info->getUserInformation()['last_name'] . ' ' .
                    ($this->user_info->getUserInformation()['suffix_name'] ?? '')
            );


            $edit_request['activity_logs'] = $activity_logs;

            $this->db->commit();
            return $this->response->buildApiResponse($edit_request, $this->response_column);
        }

        if ($request_keys === $for_archived) {

            if ($id == 0) {
                return $this->response->errorResponse("Id cannot be zero");
            }

            if ($edit_request['id'] != $id) {
                return $this->response->errorResponse("Ids Does not match");
            }


            $this->db->beginTransaction();

            if ($this->db->table($this->table)->where('id', $id)->where($edit_request)->exists()) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update. Data have Similar Status");
            }

            if (!$this->db->table($this->table)->where("id", $id)->update($edit_request)) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }

            $this->db->commit();
            return $this->response->successResponse("Data has been archived");
        }

        $payload = $this->validation->validateRequest($request, $this->accepted_parameters, $this->required_fields);
        //check if the $payload has error validation key
        if (isset($payload['error_validation'])) {
            $this->db->rollback();
            return $this->response->errorResponse($payload['message']);
        }
        if ($id !== $payload['id']) {
            return $this->response->errorResponse("Ids doesn't Match");
        }
        if ($id === 0) {
            return $this->response->errorResponse("Id must not zero");
        }
        try {

            $this->db->beginTransaction();

            $material_details = $payload['material_details'];


            unset($payload['material_details']);

            if (!$this->db->table($this->table)->where('id', $id)->update($payload)) {
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
            } else {

                if (!$this->db->table($this->table_material_details)->where('production_plan_id', $id)->delete()) {
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Update data based on monitor");
                }

                foreach ($material_details as &$md) {
                    $md['production_plan_id'] = $payload['id'];
                }

                if (!$this->db->table($this->table_material_details)->insert($material_details)) {
                    $this->db->rollbakc();
                    return $this->response->errorResponse("Can't Save Material Details");
                }
            }
        } catch (QueryException $e) {
            $this->db->rollback();
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->response->errorResponse($e);
        }
    }

    public function delete($id) {
    }

    public function upload(Request $request, $id) {
    }
}
