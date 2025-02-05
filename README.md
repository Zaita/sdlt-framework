# Project Status
This project has been deprecated and is no longer receiving updates, patches or security fixes. Due to significant breaking changes when migrating from SilverStripe 4.X to 5.X the project has been replaced with a new product developed on Laravel. 

The new SDLT, named Odin, can be found at: https://github.com/zaita/odin

# NZTA Security Development Lifecycle Tool :: Framework

The SDLT is Web Application that supports, and expedites I.T. security professionals as part of the change approval process within their organisation.

## Requirements

The SDLT is written in ReactJS and PHP and built on the [SilverStripe](https://silverstripe.org) framework.

This repository is the PHP component of [Zaita SDLT project](https://github.com/zaita/sdlt) and intended to be checked out as a dependency. View that project for setup instructions.

## Migrations
As of 3.2.0, users migrating to this version will need to set the CISO and Security Architect groups in the SiteConfig before using SDLT to receive emails. Previous versions automatically defined this, and they will stop working if the group name is changed.

## Upgrade to Silverstripe 4.8
SilverStripe 4.8 and versioned-snapshot-admin modules are using different version of Graphql. To resolve conflict please add this `"silverstripe/graphql": "3.5.1 as 3.5.0"` line in your project root "composer.json" file in the require block.
