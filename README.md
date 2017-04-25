# STAR API
Dynamic restful API that uses MongoDB as data store

### Multiple applications support

* Star API sets up database programmatically based on route request.
That way it's possible to handle multiple applications at the same time.
Prefix for all routes is : `api/v1/app/{appName}`
where appName should be database name which is then set.
