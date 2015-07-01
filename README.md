# Blowgun

[![Build Status](https://travis-ci.org/silverstripe-labs/blowgun.svg?branch=master)](https://travis-ci.org/silverstripe-labs/blowgun)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe-labs/blowgun/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe-labs/blowgun/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/silverstripe-labs/blowgun/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/silverstripe-labs/blowgun/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/silverstripe-labs/blowgun/badges/build.png?b=master)](https://scrutinizer-ci.com/g/silverstripe-labs/blowgun/build-status/master)


Blowgun is a library and tool to send and receive messages from AWS SQS. It's
mean to be deployed to an AWS web instance and fetch messages that 'commands' 
the instance to run jobs or tasks.

## Developing blowgun

Start a queue subscriber like this:

```bash
./bin/blowgun listen local `whoami` dev --node-name mynode --site-root ~/Sites/silverstripe.dev --script-dir ../scripts/
```

This will create and fetch messages from two SQS queues:
 
 1. local-{whoami}-dev-stack
 2. local-{whoami}-dev-instance-mynode
 
Since the behaviour of a SQS is that only one instance will normally receive and
work on a message, there are two different uses for these queues: 
 
The first queue is for messages where it doesn't matter which instance does the
action, for example snapshot actions.

The second queue is for messages that targets an individual instance. This can 
be used to ensure that all instances goes into maintenance mode.


