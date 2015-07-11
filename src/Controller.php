<?php

namespace PaulVL\JsonApi;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as IlluminateController;
use Illuminate\Foundation\Validation\ValidatesRequests;

use PaulVL\JsonApi\DataHandler;
use PaulVL\JsonApi\Response;
use PaulVL\Helpers\StringHelper;
use PaulVL\JsonApi\PaginationHelper;
use PaulVL\JsonApi\QueryHelper;

class Controller extends IlluminateController
{
	use DispatchesJobs, ValidatesRequests;

	/**
	 * PaulVL\JsonApi\Model class name.
	 * @var string
	 */
	protected $model_class;

    /**
     * Display a listing of the resource.
     *
     * @return PaulVL\JsonApi\Response
     */
    public function index(Request $request)
    {
        $response = new Response();

        $class = $this->model_class;

        $query = $request->get('q', null);

        $per_page = $request->get('paginate', null);

        $current_page = $request->get('page', 1);

        $data = $class::orderBy('created_at', 'ASC');

        $url = $request->url();

        if(!empty($query)) {

            $queries = QueryHelper::getQueriesFromRequest($request);

            if(!QueryHelper::validateQueriesArray($queries)) {
                return $response->responseUnprocessableEntity();
            }

            if(!$data = QueryHelper::queryData($data, $queries)) {
                return $response->responseUnprocessableEntity();
            }

            $url .= '?q=' . $query;

        }

        $data = $data->get();

        if( !empty($per_page) ) {

            if( !PaginationHelper::validatePagination($per_page, $current_page) ) {
                return $response->responseUnprocessableEntity();
            }

            $pagination_information = PaginationHelper::getPaginationInfo( $data, $per_page, $current_page, $url, (!empty($query)) );

            $data = $data->forPage($current_page, $per_page);

            $response->addMeta($pagination_information);

        }

        /* MANUAL DATA HANDLING
    	$handler = new DataHandler($class::all());
	    $response->data = $handler->getApiJsonableData(true);
        */
        //$response->withoutRelations();
        $response->handleData($data);
	    return $response->response();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return PaulVL\JsonApi\Response
     */
    public function store(Request $request)
    {
    	$class = $this->model_class;
        $response = new Response();
        $validator = Validator::make( $request->all(), $class::getRules() );
        
        if ($validator->fails()) {
            $validation_errors = StringHelper::concatInOneLine( $validator->errors()->all(), ' ' );
            return $response->responseUnprocessableEntity( $validation_errors );
        }
        
        if (method_exists($this, 'saveData')) {
            return $this->saveData( $request->all() );
        }else {
            try {
                $object = $class::create( $request->all() );
                return $response->responseCreated();
            } catch (Exception $e) {
                return $response->responseInternalServerError();
            }            
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return PaulVL\JsonApi\Response
     */
    public function show($id)
    {
        $class = $this->model_class;
        $response = new Response();
        /* MANUAL DATA HANDLING
        $handler = new DataHandler($class::findOrFail($id));
        $response->data = $handler->getApiJsonableData(true);
        */
        $response->handleData($class::findOrFail($id));
        return $response->response();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return PaulVL\JsonApi\Response
     */
    public function update(Request $request, $id)
    {
    	$class = $this->model_class;
        $response = new Response();
        $object = $class::findOrFail($id);
        $inputs = $request->all();

        $validator = Validator::make( $inputs, $object->getUpdateRules() );
        
        if ($validator->fails()) {
            $validation_errors = StringHelper::concatInOneLine( $validator->errors()->all(), ' ' );
            return $response->responseUnprocessableEntity( $validation_errors );
        }
        
        if (method_exists($this, 'updateData')) {
            return $this->updateData( $object, $inputs );
        }else {
            try {
                $updated_rows = $class::where( $object->getKeyName(), $object->getKey() )->update( $inputs );
                $object = $class::findOrFail($id);
                $response->handleData($object);
                return $response->response();
            } catch (Exception $e) {
                return $response->responseInternalServerError();
            }            
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return PaulVL\JsonApi\Response
     */
    public function destroy($id)
    {
        $class = $this->model_class;
        $response = new Response();
        $object = $class::findOrFail($id);

        try {
            $object->delete();
            return $response->response();
        } catch (Exception $e) {
            return $response->responseInternalServerError();
        }  
    }

    /**
     * Return data needed for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $response = new Response;
        return $response->responseNotFound();
    }

    /**
     * Return data needed for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $response = new Response;
        return $response->responseNotFound();
    }

}