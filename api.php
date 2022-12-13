<?php 

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    include $_SERVER['DOCUMENT_ROOT'] . '/apps/api_ingresos/sap/functions.php';

    class Api extends Rest {
        
        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

        }

        public function test(){
        }

        public function ingresos(){

            $ini = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/apps/api_ingresos/config.ini');

            $psobtyp = 'IUSI';

            if ($ini['mode'] == 'default') {

                $desde   = date('Ym') . '01';
                $hasta   = date('Ymd');

                // Formato de fecha
                $fecha_inicio = '01/'.date('m/Y');
                $fecha_fin = date('d/m/Y');

                $year = date('Y');
                $previous_year = $year - 1;

                // Fecha anterior
                $fecha_inicio_anterior = $previous_year.date('m').'01';
                $fecha_fin_anterior = $previous_year.date('md');

            }else{

                $desde   = str_replace('-', '', $ini['fecha_inicio']);
                $hasta   = str_replace('-', '', $ini['fecha_fin']);

                // Formato de fecha
                $fecha_inicio = date('d/m/Y', strtotime(str_replace('-', '/', $ini['fecha_inicio'])));
                $fecha_fin = date('d/m/Y', strtotime(str_replace('-', '/', $ini['fecha_fin'])));

                $year = date('Y', strtotime(str_replace('-', '/', $ini['fecha_fin'])));
                $previous_year = $year - 1;

                // Fecha anterior
                $fecha_inicio_anterior = $previous_year.date('md', strtotime(str_replace('-', '/', $ini['fecha_inicio'])));
                $fecha_fin_anterior = $previous_year.date('md', strtotime(str_replace('-', '/', $ini['fecha_fin'])));

            }

            // Datos actuales

            $sap = new SAP_Function();
            $result = $sap->obtenerIngresos($desde, $hasta, $psobtyp);
            $sap_return = $result;

            $total = 0;
            $data = array();
            $detalle_sap = array();

            foreach ($result as $value) {
                
                $total+=$value; 
                $detalle_sap [] = round((floatval($value) / 1000000), 2);

            }

            //Datos posteriores
            $result = $sap->obtenerIngresos($fecha_inicio_anterior, $fecha_fin_anterior, $psobtyp);

            $total_anterior = 0;
            $detalle_sap_anterior = array();

            foreach ($result as $value) {
                
                $total_anterior+=$value; 
                $detalle_sap_anterior [] = round((floatval($value) / 1000000), 2);

            }


            // Total del año
            $inicio_año = date('Y') . '0101';
            $ultima_fecha = date('Ymd');

            $result = $sap->obtenerIngresos($inicio_año, $ultima_fecha, $psobtyp);

            $total_año = 0;

            foreach ($result as $value) {
                $total_año+=$value; 
            }

            $total_año = round((floatval($total_año) / 1000000), 2);


            // Fecha de las gráficas
            $data["FECHAS"] = array(
                "FECHA_INICIO" => $fecha_inicio,
                "FECHA_FIN" => $fecha_fin
            );

            $data["TOTAL"] = $total;

            $graficas = array();

            // Grafica Comparativo
            $grafica_comparativo = array();
            $grafica_comparativo["CATEGORIES"] = array($previous_year, $year);

            $comparativo_serie = array();
            $comparativo_serie["name"] = 'IUSI';
            $comparativo_serie["data"] = array(round((floatval($total_anterior) / 1000000), 2), round((floatval($total) / 1000000), 2));

            $grafica_comparativo["SERIES"] = array($comparativo_serie);

            // Grafica de Detalle

            $grafica_detalle = array();
            $grafica_detalle["CATEGORIES"] = array("Impuesto", "Multas", "Convenios");
            $grafica_detalle["SERIES"] = array();
            
            // Serie 1 grafica detalle
            $serie1 = array();
            $serie1["name"] = $previous_year;
            $serie1["data"] = $detalle_sap_anterior;

            $grafica_detalle["SERIES"][0] = $serie1;

            // Serie 2 grafica detalle
            $serie2 = array();
            $serie2["name"] = $year;
            $serie2["data"] = $detalle_sap;

            $grafica_detalle["SERIES"][1] = $serie2;

            $graficas["GRAFICA_DETALLE"] = $grafica_detalle;
            $graficas["GRAFICA_COMPARATIVO"] = $grafica_comparativo;

            // Grafica de total de año
            $total_recaudado = round(($total_año / 500) * 100);
            $total_año = number_format($total_año,  2);
            $restante = 100 - $total_recaudado;
            $total_restante = number_format(500 - $total_año, 2);

            // Array total del año
            $total_año_ = array(
                "data" => array(
                    array(
                        "y" => $total_recaudado,
                        "color" => "green",
                        "name" => "RECAUDACIÓN",
                        "valor_total" => $total_año
                    ),
                    array(
                        "y" => $restante,
                        "color" => "red",
                        "name" => "RESTANTE",
                        "valor_total" => $total_restante
                    ),
                ),
                "meta" => 500
            );


            // CUENTAS MOROSAS
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"http://172.23.25.31/apps/api_ingresos/");
            curl_setopt($ch, CURLOPT_POST, 1);

            $data_ = array(
                "name" => "metas",
                "param" => array()
            );

            $payload = json_encode($data_);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $metas = json_decode(curl_exec($ch), true);

            $graficas["GRAFICA_TOTAL"] = $total_año_;
            $graficas["MORA"] = $metas;
            
            $data["ITEMS"] = $graficas;

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function registrar_configuracion(){

            $mode = $this->validateParameter('mode', $this->param['mode'], INTEGER);

            $file = fopen($_SERVER['DOCUMENT_ROOT'] . '/apps/api_ingresos/config.ini', "wr") or die("Unable to open file!");

            // Si el modo es 1

            if ($mode == 1) {
                
                $txt = "[fecha]\nmode = default";
				fwrite($file, $txt);

            }elseif($mode == 2){

                $fecha_inicio = $this->param["fecha_inicio"];
                $fecha_fin = $this->param["fecha_fin"];

                $txt = "[fecha]\nmode = custom\n\n[custom]\nfecha_inicio = $fecha_inicio\nfecha_fin = $fecha_fin";
				fwrite($file, $txt);

            }
            

            fclose($file);

            // Si el modo es 2

            $this->returnResponse(SUCCESS_RESPONSE, $mode);

        }

        public function obtener_configuracion(){

            $ini = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/apps/api_ingresos/config.ini');

            if ($ini['mode'] == 'default') {
                
                $data = array(
                    "config" => array(
                        "mode" => 1,
                        "time" => 60,
                        "date" => array(
                            "fecha_inicio" => '',
                            "fecha_fin" => ''
                        )
                    )
                );

            }else{

                $data = array(
                    "config" => array(
                        "mode" => 2,
                        "time" => 60,
                        "date" => array(
                            "fecha_inicio" => $ini['fecha_inicio'],
                            "fecha_fin" => $ini['fecha_fin']
                        )
                    )
                );

            }

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }
        
        public function consultar_ingresos(){

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"http://172.23.25.36/apps/api_ingresos/");
            curl_setopt($ch, CURLOPT_POST, 1);

            $data = array(
                "name" => "ingresos",
                "param" => array()
            );

            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

            $server_output = curl_exec($ch);

            // $this->returnResponse(SUCCESS_RESPONSE, $server_output);

        }

        public function app_data(){

            // Total del año
            $inicio_año = date('Y') . '0101';
            $ultima_fecha = date('Ymd');
            $psobtyp = 'IUSI';

            $sap = new SAP_Function();
            $result = $sap->obtenerIngresos($inicio_año, $ultima_fecha, $psobtyp);

            $total_año = 0;

            foreach ($result as $value) {
                $total_año+=$value; 
            }

            $total_año = round((floatval($total_año) / 1000000), 2);

            $data = array();

            // Grafica de total de año
            $total_recaudado = round(($total_año / 500) * 100);
            $total_año = number_format($total_año,  2);
            $restante = 100 - $total_recaudado;
            $total_restante = number_format(500 - $total_año, 2);

            $datos_grafica = array(
                "label" => "Ingreso",
                "value" => $total_recaudado,
                "color" => "green"
            );

            $valores_totales = array(
                "PORCENTAJE" => $total_recaudado,
                "TOTAL" => $total_año
            );

            $detalle_total = array(
                array(
                    "name" => "IUSI",
                    "value" => round((floatval($result["T_IUSI_MONTO"]) / 1000000), 2)
                ),
                array(
                    "name" => "MULTAS",
                    "value" => round((floatval($result["T_MULTA_MONTO"]) / 1000000), 2)
                ), 
                array(
                    "name" => "CONVENIOS",
                    "value" => round((floatval($result["T_CONVENIO_MONTO"]) / 1000000), 2)
                )
            );

            // Total del mes
            $inicio_mes = date('Ym') . "01";

            $totales_mes = $sap->obtenerIngresos($inicio_mes, $ultima_fecha, $psobtyp);
            
            $data["GRAFICA_TOTAL"] = $datos_grafica;
            $data["TOTALES"] = $valores_totales;
            $data["DETALLE_TOTAL"] = $detalle_total;
            $data["TOTAL_MES"] = $totales_mes;
            $data["TOTAL_ACUMULADO"] = $result;

            // $this->returnResponse(SUCCESS_RESPONSE, $data);

            echo json_encode($data);
            
        }

    }
    

?>