# CIVITAS/CORE local dev environment

CiviDash supports CIVITAS/CORE v1 from 1.6.2 to 1.8.x. All of these CORE releases
ship the same NGSI-LD broker, Stellio 2.23.0, so the API CiviDash talks to is the
same. The add-on ([cividash-addon](https://github.com/jandaroscher/cividash-addon))
supports CORE 1.7.2 to 1.8.x; its README names the version it is tested on.

The local test environment is a Stellio 2.23.0 NGSI-LD broker with the image tags
CORE pins: see [stellio/README.md](stellio/README.md).

```bash
./docker/civitas/stellio/setup.sh     # start and seed
./docker/civitas/stellio/teardown.sh  # stop and delete its volumes
```
