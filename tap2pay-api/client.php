<?PHP
namespace Tap2pay;

// This snippet (and some of the curl code) due to the Facebook SDK.
if (!function_exists('curl_init')) {
    throw new Exception('Tap2Pay needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new Exception('Tap2Pay needs the JSON PHP extension.');
}

require_once dirname(__FILE__) . "/money.php";

class Client {
    public function __construct($api_key, $host = null) {
        $this->api_key = $api_key;
        $this->host = $host;
        if($this->host == null) {
            $this->host = "https://secure.tap2pay.me";
        }
    }

    public function create_invoice($invoice_data) {
        $output = $this->request("POST", 'invoices', array('invoice' => $invoice_data));
        return json_decode($output, true);
    }

    public function update_invoice($id, $invoice_data) {
        $output = $this->request("PUT", 'invoices/'.$id, array('invoice' => $invoice_data));
        return json_decode($output, true);
    }

    protected function request($method, $path, $data) {
        $endpoint = $this->host."/api/".$path;
        $data = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $headers = array(
            "Authorization: Bearer ".$this->api_key,
        );

        switch($method) {
        case "POST":
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            break;
        case "PUT":
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            break;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return curl_exec($ch);
    }
}