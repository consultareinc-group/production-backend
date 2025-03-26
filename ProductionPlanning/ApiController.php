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
        "product_name",
        "material_name",
        "supplier_name",
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
    protected $table_supplier_material = 'supplier_material';
    protected $table_supplier = 'supplier';


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
            } else if ($request->has('search_keyword') || $request->has('start_date_and_time') || $request->has('end_date_and_time') || $request->has('status')) {
                $search_keyword = $request->query('search_keyword', '');
                $start_date_and_time = $request->query('start_date_and_time', '');
                $end_date_and_time = $request->query('end_date_and_time', '');
                $status = $request->query('status', '');

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
                    ->where('pp.is_archived', 0);

                if (!empty($search_keyword)) {
                    $query_result->where(function ($query) use ($search_keyword) {
                        $query->where('pp.batch_number', 'like', '%' . $search_keyword . '%')
                            ->orWhere('p.name', 'like', '%' . $search_keyword . '%')
                            ->orWhere('pp.quantity', 'like', '%' . $search_keyword . '%')
                            ->orWhere('pp.customer_name', 'like', '%' . $search_keyword . '%')
                            ->orWhere('pp.status', 'like', '%' . $search_keyword . '%');
                    });
                }

                if (!empty($start_date_and_time) && !empty($end_date_and_time)) {
                    $query_result->where(function ($query) use ($start_date_and_time, $end_date_and_time) {
                        $query->whereBetween('pp.start_date_and_time', [$start_date_and_time, $end_date_and_time])
                            ->orWhereBetween('pp.end_date_and_time', [$start_date_and_time, $end_date_and_time])
                            ->orWhere(function ($query) use ($start_date_and_time, $end_date_and_time) {
                                $query->where('pp.start_date_and_time', '<=', $start_date_and_time)
                                    ->where('pp.end_date_and_time', '>=', $end_date_and_time);
                            });
                    });
                } else if (!empty($start_date_and_time)) {
                    $query_result->where('pp.start_date_and_time', '>=', $start_date_and_time);
                } else if (!empty($end_date_and_time)) {
                    $query_result->where('pp.end_date_and_time', '<=', $end_date_and_time);
                }

                if ($status !== '') {
                    $query_result->where('pp.status', $status);
                }

                $query_result = $query_result->orderBy('pp.id', 'desc')->get();
            } else {
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
                    ->get();
            }


            if($request->has('product_name')){
                $product_search = $request->query('product_name','');
                $query_result = $this->db->table($this->table_products)
                                            ->select(
                                                'id',
                                                'name as product_name' 
                                                )
                                            ->where('name', 'like', '%' . $product_search . '%')
                                            ->get();


            }

            if($request->has('material_name')){
                $material_search = $request->query('material_name','');
                $query_result = $this->db->table($this->table_supplier_material)
                                            ->select(
                                                'id',
                                                'material_name' 
                                                )
                                            ->where('material_name', 'like', '%' . $material_search . '%')
                                            ->get();


            }

            if($request->has('supplier_name')){
                $supplier_search = $request->query('supplier_name','');
                $query_result = $this->db->table($this->table_supplier)
                                            ->select(
                                                'id',
                                                'name as supplier_name' 
                                                )
                                            ->where('name', 'like', '%' . $supplier_search . '%')
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

        // Check if the $payload has an error validation key
        if (isset($payload['error_validation'])) {
            return $this->response->errorResponse($payload['message']);
        }

        // Validate material_details array
        if (isset($payload['material_details'])) {
            if (!is_array($payload['material_details'])) {
                return $this->response->errorResponse("Invalid material details format. It should be an array.");
            }

            foreach ($payload['material_details'] as $index => $md) {
                if (
                    !isset($md['material_id']) ||
                    !isset($md['supplier_id']) ||
                    !isset($md['description']) ||
                    !isset($md['uom']) ||
                    !isset($md['lot_number']) ||
                    !isset($md['amount']) ||
                    !isset($md['batch']) ||
                    !isset($md['amount_issued_date_and_time']) ||
                    !isset($md['pick_location'])
                ) {
                    return $this->response->errorResponse("Material details at index $index is missing required fields.");
                }

                // Validate amount_issued_date_and_time format
                if (!strtotime($md['amount_issued_date_and_time'])) {
                    return $this->response->errorResponse("Invalid date format for material details at index $index.");
                }
            }
        }

        try {
            $this->db->beginTransaction();

            $material_details = $payload['material_details'] ?? [];
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

                if (!empty($material_details) && !$this->db->table($this->table_material_details)->insert($material_details)) {
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Material Details");
                }

                $activity_logs_data = [
                    'production_plan_id' => $payload['id'],
                    'personnel_id' => $this->user_info->getUserId(),
                    'action' => $payload['status'],
                    'date_and_time' => now()->format('Y-m-d H:i:s')
                ];

                $activity_logs_data['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_logs_data);

                if (!$activity_logs_data['id']) {
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Activity Logs");
                } else {
                    foreach ($material_details as $md) {
                        $payload['material_details'][] = [
                            'material_id' => $md['material_id'],
                            'supplier_id' => $md['supplier_id'],
                            'description' => $md['description'],
                            'uom' => $md['uom'],
                            'lot_number' => $md['lot_number'],
                            'amount' => $md['amount'],
                            'batch' => $md['batch'],
                            'amount_issued_date_and_time' => $md['amount_issued_date_and_time'],
                            'pick_location' => $md['pick_location']
                        ];
                    }

                    $payload['activity_logs'] = $activity_logs_data;

                    $this->db->commit();

                    return $this->response->buildApiResponse($payload, $this->response_column);
                }
            }
        } catch (QueryException $e) {
            $this->db->rollback();
            return $this->response->errorResponse($e->getMessage());
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->response->errorResponse($e->getMessage());
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
                "production_plan_id" => $id,
                "action" => $edit_request['status'],
                "user_id" => $this->user_info->getUserId(),
                'date_and_time' => now()->format('Y-m-d H:i:s')
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

                if (!empty($material_details) && !$this->db->table($this->table_material_details)->insert($material_details)) {
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Material Details");
                }
        

                $this->db->commit();

                $payload['material_details'] = $material_details;

                return $this->response->buildApiResponse($payload, $this->response_column);            
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
