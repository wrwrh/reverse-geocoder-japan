# reverse-geolocation-japan
A simple system of translate latitude and langitude to address for japan.

## usage
```
gis.php?lat={latitude}&lng={longitude}
```
- response (example for lat=35 lng=136)
```
{
    "accuracy": 194,
    "geo": {
        "lat": 35.001165,
        "lng": 136.001515,
        "pref": "滋賀県",
        "city": "栗東市",
        "district": "下戸山",
        "street": "",
        "numbers": "518-586"
    }
}
```

## create sqlite-db for gis.php
- Download csv files from https://nlftp.mlit.go.jp/cgi-bin/isj/dls/_choose_method.cgi and expand zip.
- Save all csv files in zip for any directory.
- Edit variables (dir, sdir and db_path) in createGISdb.py for adapte your environment and run createdb.py.
