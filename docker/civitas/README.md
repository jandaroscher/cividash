# CIVITAS/CORE local dev environment

CiviDash supports CIVITAS/CORE v1 (1.6.x) only. The add-on
([cividash-addon](https://github.com/jandaroscher/cividash-addon))
targets the same version.

The local test environment is a Stellio NGSI-LD broker aligned with CORE 1.6.2:
see [v1.6.2/README.md](v1.6.2/README.md).

```bash
./docker/civitas/v1.6.2/setup.sh     # start and seed
./docker/civitas/v1.6.2/teardown.sh  # stop and remove its data
```
