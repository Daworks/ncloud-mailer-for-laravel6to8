<?php

	namespace Daworks\NcloudMailer;

	use Illuminate\Mail\Transport\Transport;
	use Swift_Mime_SimpleMessage;
	use GuzzleHttp\Client;

	class NcloudMailerDriver extends Transport
	{
		protected $baseUri = 'https://mail.apigw.ntruss.com';
		protected $apiEndpoint = '/api/v1/mails';
		protected $fileApiEndpoint = '/api/v1/files';
		protected $authKey;
		protected $serviceSecret;
		protected $client;

		public function __construct(string $authKey, string $serviceSecret)
		{
			$this->authKey = $authKey;
			$this->serviceSecret = $serviceSecret;
			$this->client = new Client([
				'base_uri' => $this->baseUri,
			]);
		}

		public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
		{
			$this->beforeSendPerformed($message);

			$attachments = $this->uploadAttachments($message);

			$timestamp = $this->getTimestamp();
			$signature = $this->makeSignature($timestamp, 'POST', $this->apiEndpoint);

			$response = $this->client->post($this->apiEndpoint, [
				'headers' => [
					'Content-Type'             => 'application/json',
					'x-ncp-apigw-timestamp'    => $timestamp,
					'x-ncp-iam-access-key'     => $this->authKey,
					'x-ncp-apigw-signature-v2' => $signature,
					'x-ncp-lang'               => 'ko_KR'
				],
				'json'    => $this->formatEmailData($message, $attachments),
			]);

			if ($response->getStatusCode() !== 201) {
				throw new \Exception('Failed to send email: ' . $response->getBody());
			}

			$this->sendPerformed($message);

			return $this->numberOfRecipients($message);
		}

		protected function formatEmailData(Swift_Mime_SimpleMessage $message, array $attachments): array
		{

			$data = [
				'senderAddress' => array_keys($message->getFrom())[0],
				'senderName'    => $message->getFrom()[array_keys($message->getFrom())[0]],
				'title'         => $message->getSubject(),
				'body'          => $message->getBody(),
				'recipients'    => [
					[
						'address' => array_keys($message->getTo())[0],
						'name'    => $message->getTo()[array_keys($message->getTo())[0]],
						'type'    => 'R',
					]
				],
				'individual'    => true,
				'advertising'   => false,
			];

			if (!empty($attachments)) {
				$data['attachFileIds'] = $attachments;
			}

			return $data;
		}

		protected function uploadAttachments(Swift_Mime_SimpleMessage $message): array
		{
			$attachments = [];

			foreach ($message->getChildren() as $child) {

				try {

					$timestamp = $this->getTimestamp();
                    $file_name = $child->getFilename();
                    $dest_path = storage_path('app/public/temp/');

                    if ( ! is_dir($dest_path)) {
                        mkdir($dest_path, 0755, true);
                    }

                    $file_path = storage_path('app/public/temp/' . $file_name);

                    if ( file_exists($file_path) ) {
                        unlink($file_path);
                    }

                    file_put_contents($file_path, $child->getBody());

                    $file_mime = mime_content_type($file_path);

                    // Boundary 설정
                    $delimiter = uniqid();

                    // 파일 데이터 생성
                    $data = '';
                    $data .= "--" . $delimiter . "\r\n";
                    $data .= 'Content-Disposition: form-data; name="fileList";' . ' filename="' . $file_name . '"' . "\r\n";
                    $data .= 'Content-Type: ' . $file_mime . "\r\n";
                    $data .= "\r\n";
                    $data .= file_get_contents($file_path) . "\r\n";
                    $data .= "--" . $delimiter . "--\r\n";

                    $headers = [
                        'Content-Type: multipart/form-data; boundary=' . $delimiter,
                        'x-ncp-iam-access-key: ' . config('ncloud-mailer.auth_key'),
                        'x-ncp-apigw-signature-v2: ' . $this->makeSignature($timestamp, 'POST', '/api/v1/files'),
                        'x-ncp-apigw-timestamp: ' . $timestamp,
                    ];

                    // cURL 초기화
                    $ch = curl_init();

                    $url = $this->baseUri . $this->fileApiEndpoint;

                    // cURL 옵션 설정
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

                    // 요청 실행 및 응답 처리
                    $response = curl_exec($ch);

                    // 오류 처리
                    if (curl_errno($ch)) {
                        $error_msg = curl_error($ch);
                        curl_close($ch);

                        throw new \Exception($error_msg);
                    }

                    curl_close($ch);

                    $responseBody = json_decode($response, true);

                    unlink($file_path);
					$attachments[] = $responseBody['files'][0]['fileId'];

				} catch (\Exception $e) {
					throw new \Exception('HTTP request failed: ' . $e->getMessage());
				}
			}

			return $attachments;
		}

		protected function makeSignature($timestamp, $method = 'POST', $uri = '/api/v1/mails')
		{
			$space = " ";
			$newLine = "\n";
			$accessKey = $this->authKey;
			$secretKey = $this->serviceSecret;

			$hmac = $method . $space . $uri . $newLine . $timestamp . $newLine . $accessKey;

			return base64_encode(hash_hmac('sha256', $hmac, $secretKey, true));
		}

		protected function getTimestamp()
		{
			return round(microtime(true) * 1000);
		}

	}
