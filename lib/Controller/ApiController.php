<?php
    namespace OCA\BigBlueButton\Controller;

    use OCP\IRequest;
    use OCP\AppFramework\ApiController;
    use OCP\AppFramework\Http;
    use OCP\AppFramework\Http\DataResponse;
    use OCA\BigBlueButton\Db\Room;
    use OCA\BigBlueButton\Service\RoomService;

    use Sabre\HTTP\Util;

    class MeetingApiController extends ApiController {
        /** @var RoomService */
        private $service;

        /**
         * @param string $appName
         * @param IRequest $request
         * @param RoomService $service
         * @param $userId
         */
        public function __construct($appName, IRequest $request, RoomService $service, $userId) {
            parent::__construct($appName, $request);
            $this->service = $service;
            $this->userId = $userId;
        }


        /**
         * @NoAdminRequired
         * @CORS
         * @NoCSRFRequired
         *
         * @params $meetingName
         * @params $welcomeMessage
         * @params $record
         * @params $access
         * @params $maxPart
         * 
         * TODO: change default Meeting Names
         * Creates the Meeting with the specified name in Json
         */
        public function createMeeting(string $meetingName, string $welcomeMessage, int $maxPart, bool $record, string $access) {
            return new DataResponse($this->service->create(
                $meetingName,
                $welcomeMessage,
                $maxPart,
                $record,
                $access,
                true,
                $this->userId
            ));
        }


        /**
         * @NoAdminRequired
         * @CORS
         * @NoCSRFRequired
         *
         * TODO: change default Meeting Names
         * Creates the Meeting with the specified name in Json
         */
        public function getMeetingsFromUserId() {
            return new DataResponse($this->service->findByUserId( $this->userId ));
        }

    }