# Blowgun

## Starting locally

```bash
./bin/blowgun listen cluster stack environment ~/Sites/mysite ./scripts
```

This will start blowgun that fetches messages from a queue named `cluster-stack-environment`.

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

