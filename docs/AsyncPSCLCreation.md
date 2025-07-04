# PSCL Creation becoms Async
# ===========================

- 5 Gen 2024

## Introduction
PSCL Generation can require a lot of processing time, depending on the size of the company and the power of the CPU.
This leads to several timeouts, which are not always easy to handle.

## Solution
The solution is to make the PSCL Generation asynchronous, so that the user can continue working while the PSCL is being generated.
We are using a standard queue: [beanstalkd], which is a simple, fast work queue.

## Implementation
The implementation is done in the following steps:
- PSCL generation is now sepeate in a authonomous controller: `PSCLController`
- When you call report, the controller will create a new job in the queue, and return the job id and the name of the **tube** used (Moma-pscl-exporter)
- Parameters for the generator PSCLController.report() are:
    - format (one of the supported format classes)
    - company_id
    - office_id
    - survey_id
    - ignore_office (if true the survey analytics will ignore the office_id and will generate the charts for the whole company)

## Exporter Classes Supported
- The PSCL generator has a method "report" which call the specific exporer for the specific type of PSCL, at the moment the following formats are supported:
    - docx (via [PHPWord])
    - html (replacing a mustache template into a pre-set HTML file)
    - md (generates a set of md files in a folder /pscl/office_id), the md files are generated from the templates contained in 
        /pscl/template-azienda (or /pscl/template-scuola) - where the second part of the name is the type of company
        assets and shared md files (accross companies) are in /pscl/shared
        the report generator has a two functions:
            - convert MD to HTML
            - include all the files in a single HTML file (starting from index.html)
    - pdf (will use [Weasyprint] to convert the HTML to PDF with typographic quality)

## How to run the queue
The queue is managed by [beanstalkd], which is a simple, fast work queue.

You need to install beanstalkd on your system, then you can run it with the following command:
```bash
    sudo apt install beanstalkd
```

Be sure that the queue is running using the following command:
```bash
    sudo service beanstalkd start
```
The queue is running. Now the PSCL generator can send elements to the queue

In order to actually run the queue, you need to run the worker (ideally on a cronjob every minute or less):
```bash
    HTTP_HOST=yourdomain.com bin/cake beanstalk_worker
```

This command will run a cakephp command that actually does the requested long task.

In the folder `webroot/pscl/`, a folder called `template-azienda` should exist.

## Queue management
You can run [https://github.com/xuri/aurora](Aurora) to see what is happening in the background
To install using Docker and run, use the following command:
```bash
    docker run --rm --detach --network="host" aurora:latest
```
Open `localhost:3000` and connecto server `localhost:11300`

[beanstalkd]: https://beanstalkd.github.io/
[PHPWord]: https://github.com/PHPOffice/PHPWord
[Weasyprint]: https://weasyprint.org/
