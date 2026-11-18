<?php

/*
 * Entry point used when YCC Hub sits inside public_html and is opened as a
 * subfolder, e.g. https://conservems.my/ycc-hub
 *
 * If a subdomain points straight at the public/ folder instead
 * (e.g. https://ycc.conservems.my), this file is not used.
 */

require __DIR__.'/public/index.php';
