boombatower/source-broadcast-proxy
==================================
Forwards Valve's Source Engine LAN broadcast packets to non-default ports.

By proxying the broadcast packets more than the default 5 Source servers can be hosted on the same
machine/IP while still being displayed in the LAN server browser. Great for LAN parties with
powerful servers (such as large organized ones).

This implementation is designed to work with Source servers hosted in Docker containers that expose
one of the default 5 ports. No configuration is necessary as the proxy will detect running
containers and scan the exposed ports to determine which are potential Source servers.

Docker configuration
--------------------
Docker must be configured to expose its API via a TCP interface. See the relevant
[installation instructions](http://docs.docker.com/installation/) for information on configuration
location and such. It may already be configured to expose the API and there is no harm in just
running the proxy and see if it blows up.

For most setups something like the following should be in the configuration file in addition to
any other options.

    DOCKER_OPTS="-H tcp://127.0.0.1:2375"

If a non-default port is used it must be communicated to the proxy during startup by adding the
following to the command in the usage section (with the relevant port).

    -e DOCKER_PORT=13370

Usage
-----
In general the following command should do the trick.

``` sh
$ docker run -d --net host --restart 'on-failure' boombatower/source-broadcast-proxy
```

`-d` Detaches since there should not be any need to watch the proxy.

The reason for `--net host` is to ensure easy access to Docker API port without having to setup
forwarding rules and what not from the Docker network device to the host. It also simplifies
forwarding to the Source servers since they can be accessed directly on the host ports instead of
needing to be accessed via the Docker gateway.

`--restart 'on-failure'` does exactly what it says to ensure the proxy stays running.

Source server containers
------------------------
Either build Docker images for the Source servers you are interested in hosting and/or search for
existing images to start from. For example: Google `docker tf2`.

Since the port does not matter disregard any instructions about mapping ports using `-p`. For
example mapping the default Source port to the host `-p 27015:27015/udp`. Instead use the upper-case
`-P` to have Docker pick a free port on the host so one does not have to bother keeping track of
ports since the proxy will do so.

Fakes servers
-------------
To test a setup ahead of time it is recommended launch a bunch of lightweight fake servers using
[boombatower/source-server-fake](https://github.com/boombatower/source-server-fake).

Result
------
Host as many Source servers in Docker containers as desired and have them all show up in the LAN
server browser!

![Screenshot of Steam server browser LAN tab](screenshot.browser.png)

Example of running Docker containers

    CONTAINER ID        IMAGE                                       COMMAND                CREATED              STATUS              PORTS                      NAMES
    0b8bd698a033        boombatower/source-broadcast-proxy:latest   "/usr/bin/php proxy.   About a minute ago   Up About a minute                              source-broadcast-proxy
    9922fefab279        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49199->27015/udp   agitated_hoover
    ebf2edaa5be1        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49198->27015/udp   sleepy_pare
    be25aa3ead96        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49200->27015/udp   happy_curie
    81de73a90d06        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49193->27015/udp   happy_darwin
    1f6163b48d71        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49197->27015/udp   backstabbing_tesla
    2aa213eb91e7        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49196->27015/udp   stupefied_shockley
    271b17e7bbcb        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49195->27015/udp   drunk_hoover
    06b4d4a1d7f7        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49194->27015/udp   grave_lalande
    1e54128e39d7        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49190->27015/udp   high_sinoussi
    9ecfaa7d9eda        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49192->27015/udp   jovial_bardeen
    0263ed6168b9        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49191->27015/udp   stoic_babbage
    aec1f91d7e03        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49189->27015/udp   stoic_shockley
    25ebb8033f4b        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49187->27015/udp   elegant_poincare
    85dbaa4ebca7        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49188->27015/udp   kickass_kirch
    4dab148d160c        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49186->27015/udp   loving_bell
    4b8033ecb57a        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49185->27015/udp   prickly_bohr
    58d9a1fc3481        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49183->27015/udp   cranky_stallman
    91c2eb23c7ef        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49184->27015/udp   boring_franklin
    eb5a975ac308        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49182->27015/udp   dreamy_archimedes
    efa55e1a37ce        boombatower/source-server-fake:latest       "/usr/bin/php fake.p   About a minute ago   Up About a minute   0.0.0.0:49181->27015/udp   sleepy_turing

Example Proxy output

    Received A2S_INFO request from 192.168.7.17:43781
    -> Forwarding to Source server on port 49199
    -> Forwarding to Source server on port 49198
    -> Forwarding to Source server on port 49200
    -> Forwarding to Source server on port 49193
    -> Forwarding to Source server on port 49197
    -> Forwarding to Source server on port 49196
    -> Forwarding to Source server on port 49195
    -> Forwarding to Source server on port 49194
    -> Forwarding to Source server on port 49190
    -> Forwarding to Source server on port 49192
    -> Forwarding to Source server on port 49191
    -> Forwarding to Source server on port 49189
    -> Forwarding to Source server on port 49187
    -> Forwarding to Source server on port 49188
    -> Forwarding to Source server on port 49186
    -> Forwarding to Source server on port 49185
    -> Forwarding to Source server on port 49183
    -> Forwarding to Source server on port 49184
    -> Forwarding to Source server on port 49182
    -> Forwarding to Source server on port 49181
    Handled in 0.010501 seconds
    Received A2S_INFO request from 192.168.7.17:34473
    -> Sending 20 cached responses
    Handled in 0.000104 seconds
