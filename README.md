# Blowgun

Blowgun is a library and tool to send and receive messages from AWS SQS. It's
mean to be deployed to an AWS web instance and fetch messages that 'commands' 
the instance to run jobs or tasks.

Some examples of tasks:

 - sspak snapshot restore and store
 - set web site in maintenance mode 

## Developing blowgun

Start a queue subscriber like this:

```bash
./bin/blowgun listen local `whoami` dev --node-name a --site-root ~/Sites/sandbox.dev --script-dir ./scripts/
```

This will create and fetch messages from two SQS queues:
 
 1. local-{yourname}-dev-stack
 2. local-{yourname}-dev-instance-a
 
Since the behaviour of a SQS is that only one instance will normally receive and
work on a message, there are two different uses for these queues: 
 
The first queue is for messages where it doesn't matter which instance does the
action, for example snapshot actions.

The second queue is for messages that targets an individual instance. This can 
be used to ensure that all instances goes into maintenance mode.

## Release a new version of blowgun

```bash
./release.sh
```

## Download and install blowgun on an instance

NOTE: This is currently not fully working since blowgun listening queue is hardcoded. 

```bash
curl -o blowgun.deb https://s3-ap-southeast-2.amazonaws.com/ss-packages/blowgun/blowgun_1.0.0.deb
dpkg -i blowgun.deb
```

