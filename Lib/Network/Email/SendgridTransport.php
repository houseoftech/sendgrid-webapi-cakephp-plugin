<?php

App::uses('AbstractTransport', 'Network/Email');
App::uses('HttpSocket', 'Network/Http');

class SendgridTransport extends AbstractTransport {
    /**
     * CakeEmail
     *
     * @var CakeEmail
     */
    protected $_cakeEmail;

    /**
     * CakeEmail headers
     *
     * @var array
     */
    protected $_headers;

    /**
     * Configuration to transport
     *
     * @var mixed
     */
    protected $_config = array();

    /**
     * Recipients list
     *
     * @var mixed
     */
    protected $_recipients = array();

    /**
     * Sends out email via SendGrid
     *
     * @return bool
     */
    public function send(CakeEmail $email) {

        // CakeEmail
        $this->_cakeEmail = $email;

        $this->_config = $this->_cakeEmail->config();

        if (empty($this->_config['count']) || $this->_config['count'] > 500) {
            $this->_config['count'] = 500;
        }

        $this->_headers = $this->_cakeEmail->getHeaders();
        $this->_recipients = $email->to();

        return $this->_sendPart();

    }

    private function _sendPart() {

        if(empty($this->_recipients)) {
            return true;
        }

        $json = array(
            'category' => !empty($this->_headers['X-Category']) ? $this->_headers['X-Category'] : $this->_config['category'],
            'unique_args' => !empty($this->_headers['X-Unique-Args']) ? $this->_headers['X-Unique-Args'] : $this->_config['unique_args']
        );
        
        $toRecipients = $this->_splitAddress(array_splice($this->_recipients, 0, $this->_config['count']));
        $cc = $this->_cakeEmail->cc();
        $ccRecipients = $this->_splitAddress($cc);
        $bcc = $this->_cakeEmail->bcc();
        $bccRecipients = $this->_splitAddress($bcc);

        //Sendgrid Substitution Tags
        if (!empty($this->_headers['X-Sub'])) {
            foreach ($this->_headers['X-Sub'] as $key => $value) {
                $json['sub'][$key] = array_splice($value, 0, $this->_config['count']);
            }
        }

        $params = array(
            'api_user'  => $this->_config['username'],
            'api_key'   => $this->_config['password'],
            'x-smtpapi' => json_encode($json),
            'to'        => $toRecipients['email'],
            'toname'    => $toRecipients['name'],
            'cc'        => $ccRecipients['email'],
            'ccname'    => $ccRecipients['name'],
            'bcc'       => $bccRecipients['email'],
            'bccname'   => $bccRecipients['name'],
            'subject'   => $this->_cakeEmail->subject(),
            'html'      => $this->_cakeEmail->message('html'),
            'text'      => $this->_cakeEmail->message('text'),
            'from'      => $this->_config['from'],
            'fromname'  => $this->_config['fromName'],
        );

        $attachments = $this->_cakeEmail->attachments();
        if (!empty($attachments)) {
            foreach ($attachments as $key => $value) {
                $params['files[' . $key . ']'] = '@' . $value['file'];
            }
        }

        $result = json_decode($this->_exec($params));

        if ($result->message != 'success') {
            return  $result;
        } else {
            return $this->_sendPart();
        }
    }
    
    private function _splitAddress($addresses = array()) {

        $output = array(
	       'email' => array(),
	       'name' => array()
        );

        foreach($addresses as $key => $value) {
	       $output['email'][] = $key;
	       if ($key == $value) {
		      $output['name'][] = '';
	       } else {
	           $output['name'][] = $value;
	       }
        }

        return $output;
    }

    private function _exec($params) {
        $request =  'http://sendgrid.com/api/mail.send.json';
        $email = new HttpSocket();
        $response = $email->post($request, $params);
        return $response->body;
    }

}
