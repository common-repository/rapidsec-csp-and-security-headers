# RapidSec - CSP and Security Headers
Contributors: nikatrapidsec,shairapidsec
Tags: csp, content-security-policy, security, security headers, xss, magecart
Requires at least: 5.0
Requires PHP: 7.2
Tested up to: 5.7
Stable tag: 1.3.4
License: GPLv3 or later
License URI: <http://www.gnu.org/licenses/gpl-3.0.html>

## Description
This plugin helps you protect your Wordpress site and admin panel from various client-side cyber attacks, such as XSS, formjacking and Magecart.
It links with the Rapidsec service to automatically generate your Content-Security-Policy (CSP) and security headers, and monitor for attacks in realtime.

## Installation and Setting Up RapidSec

1. Download the [Latest RapidSec plugin](https://api.rapidsec.com/agents/wordpress_agent/latest/download) and install it on your site, by uploading to the `/wp-content/plugins/` directory (or via the zip).

2. Activate the plugin through the 'Plugins' menu in WordPress

3. In order to integrate the plugin - you will need two RapidSec tokens.
   One for your wp-admin/ panel, and one for the user-facing site. Open account at [Rapidsec](https://rapidsec.com) and create two projects.

3. Copy your project API keys from the "Microagent (Automatic)" section - under Wordpress and add them to the plugin settings.

4. Save!

5. RapidSec will ask you which assets to approve being loaded on your site. 

### RapidSec Product Tour - Protecting your site with Security Headers
https://www.youtube.com/watch?v=SC5-PzuboQo

## Privacy
This WordPress plugin does not collect or track additional data or usage information. For full term of use on the Rapidsec product as well as privacy policy, please see https://rapidsec.com/customer-terms , https://rapidsec.com/privacy

## Frequently Asked Questions

None

## Changelog

1.0 Fully working version

## Upgrade notice

None