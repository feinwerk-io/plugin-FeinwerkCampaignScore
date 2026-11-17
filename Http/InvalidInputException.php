<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Http;

use Piwik\Http\HttpCodeException;

/**
 * Invalid input to the API: HTTP 400 (core's HttpCodeException). Not core's BadRequestException: on Matomo 5.0.0 its
 * constructor drops the code (parent::__construct($message) without it), so the status would stay 200 there (found by
 * Page Filter Report's 5.x CI; same class there).
 */
final class InvalidInputException extends \Exception implements HttpCodeException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 400);
    }
}
