<?php

use DBA\AccessGroupAgent;
use DBA\Agent;
use DBA\QueryFilter;
use DBA\RegVoucher;
use DBA\Factory;

class APIRegisterAgent extends APIBasic {
  public function execute($QUERY = array()) {
    //check required values
    if (!PQueryRegister::isValid($QUERY)) {
      $this->sendErrorResponse(PActions::REGISTER, "Invalid registering query!");
    }

    $qF = new QueryFilter(RegVoucher::VOUCHER, $QUERY[PQueryRegister::VOUCHER], "=");
    $voucher = Factory::getRegVoucherFactory()->filter([Factory::FILTER => $qF], true);
    if ($voucher == null) {
      $this->sendErrorResponse(PActions::REGISTER, "Provided voucher does not exist.");
    }

    $name = htmlentities($QUERY[PQueryRegister::AGENT_NAME], ENT_QUOTES, "UTF-8");

    //create access token & save agent details
    $token = Util::randomString(10);
    $agent = new Agent(0, $name, "", -1, "", "", 0, 1, 0, $token, PActions::REGISTER, time(), Util::getIP(), null, 0, "");

    if (SConfig::getInstance()->getVal(DConfig::VOUCHER_DELETION) == 0) {
      Factory::getRegVoucherFactory()->delete($voucher);
    }
    $agent = Factory::getAgentFactory()->save($agent);
    if ($agent != null) {
      $payload = new DataSet(array(DPayloadKeys::AGENT => $agent));
      NotificationHandler::checkNotifications(DNotificationType::NEW_AGENT, $payload);

      // assign agent to default group
      $accessGroup = AccessUtils::getOrCreateDefaultAccessGroup();
      $accessGroupAgent = new AccessGroupAgent(0, $accessGroup->getId(), $agent->getId());
      Factory::getAccessGroupAgentFactory()->save($accessGroupAgent);

      $this->sendResponse(array(
          PQueryRegister::ACTION => PActions::REGISTER,
          PResponseRegister::RESPONSE => PValues::SUCCESS,
          PResponseRegister::TOKEN => $token
        )
      );
    }
    else {
      $this->sendErrorResponse(PActions::REGISTER, "Could not register you to server: Saving failed!");
    }
  }
}