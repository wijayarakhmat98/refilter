# Linear crawl

Given a url in the form of '...{id}...', a crawling can be done by incrementing the id.

For example, given the following url:

> [https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401264](https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401264)

38401264 is the id that can be incremented, crawling linearly the urls that follows:

> [https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401265](https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401265)\
> [https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401266](https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401266)\
> [https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401267](https://sirup.lkpp.go.id/sirup/home/detailPaketPenyediaPublic2017/38401267)\
> and so on...

Therefore, given a lower bound and an upper bound for the ids, all the corresponding urls can be crawled.

For example,

```
... o o | @ o o o ... o o o @ | o o ...
          38401264          38401299
```

where 38401264 is the lower bound, and 38401299 is the upper bound, urls within and on these bounds will be crawled, while those outside will not.

# Unknown bound

Given a known lower bound id, the upper bound may be unknown.

For example,

```
... o o | @ o o o ... o o o x x x x x ...
          38401264

o: exists
x: does not exists
```

A crawling can be done continuously until a corresponding url don't exist.

The same scenario with a known upper bound and unknown lower bound applies. This results in two kinds of linear crawling, ascending crawl and descending crawl, where the ids are incremented and decremented respectively.

Restrictions of the unbounded as a maximum or a minimum can still be applied, to prevent the crawl from going indefinitely, where crawling will stop whenever a nonexistent url encountered or a maximum/minimum is reached, whichever comes first.

What this entails is, an upper bound and a lower bound are specified, and either an ascending or a descending crawl is done.

For example, if the upper bound is known to exists but the lower bound isn't then a descending crawl is appropriate, where it will start from the upper bound and decrement to the lower bound, until it reaches it or stop early if a nonexistent url is encountered.

```
                                    <<==@
... x x x | # x x x x x o o o ... o o o @ | o o ...
            38071127                    38401264
```

# Margin

Not all ids have a corresponding url. That is, some id intervals contain gaps.

```
... x x x x x o o o ... o o x o o o o x o o o x x o o x o o o ... o o o x x x x x ...
```

Therefore, stopping a crawl immediately when encountering a url that don't exist may be premature.

As such, a margin can be used where, after a certain number of urls that doesn't exists have been encountered, the crawl will be terminated.

Whether there exists urls beyond the margin are purely business decision. Determining an acceptable value for a margin requires some educated guess, past experience, and analysis. Too large of a margin means spending extra time crawling contents that may not exists, too little means there are chances of missing out on valuable contents.

For example, the following illustrates an ascending crawl with a margin or 3.

```
        @==>>                         STOP
... o | @ o o x o o o x x o x o o x x % x x x # | x ...
```

Hence, it can be seen that margin is not ascending or descending agnostic. It is a heuristic used by the crawler to determine when to terminate. The effect of using the same bounds and margin will, in general, result differently on an ascending or a descending crawl.

# Attempt

Sometimes, although the corresponding url of an id exists, due to various reason, however, request for content may not be successful the first time around. As such, having the ability to reattempt a request after some cool-down time is desirable.

```
    Attempt | @==>>
          1 | @ o o o x o o o o o x x o o x o o x x x ...
(delayed) 2 |         x           o x     o     x x x ...
          . |
          . |
          . |                                       STOP
(delayed) n |         o             o           x x x ...
```

# Interleaved

In most cases, both the lower and upper boundaries are unknown. Given a known starting point, it is possible to use it to divide a maximum and minimum restriction into two subintervals, then performs a descending crawl towards the minimum and an ascending crawl towards the maximum from that starting point.

```
        [ min, ...                                 ..., max ]

        [ min, ...     ..., x - 1 ]
                            <<==@
... x x | # x x x o o ... o o o @ | @ o o o ... o o x x x # | x x ...
                                    @==>>
                                  [ x, ...         ..., max ]
```

Note that, this means the two crawls are done consecutively. That is, for example, the descending crawl will be started only after the ascending crawl has finished. This is, however, rarely a desirable behavior.

To balance the crawling process, an interleaved method is developed. Where, as the ids are incremented and decremented, after one fetch had been done by the ascending crawl one fetch will be done by the descending crawl before coming back for another fetch on the ascending crawl, and so on.

This procedure also has the benefit of enabling to interleave many different crawl, not limited to just a pair of crawl from a single urls domain.

```
Domain a (ascending ) | ... x x x . . ... . . . . A A A A A A A A ... A A A x x x ...
Domain a (descending) | ... x x x x a ... a a a a . . . . . . . . ... o o o x x x ...
Domain b (ascending ) | ... x x x . . ... . . . . . . . . B B B B ... B x x x x x ...
Domain b (descending) | ... x x x b b ... b b b b b b b b . . . . ... . . x x x x ...
                      |--------------------------------------------------------------
              Fetch 1 |                           A
              Fetch 2 |                         a
                    . |                                   B
                    . |                                 b
                    . |                             A
                    . |                       a
                    . |                                     B
                    . |                               b
```

# Rate

When interleaving tasks, the starting point may be closer to the upper boundary than the lower boundary. Therefore, it makes sense to fetch more often from the descending crawl than the ascending crawl.

```
Domain a, ascent , rate 1 | ... x x x . . ... . . . . A A A A A A A A ... A A A x x x ...
Domain b, descent, rate 2 | ... x x x b b ... b b b b b b b b . . . . ... . . x x x x ...
                          |--------------------------------------------------------------
                  Fetch 1 |                           A
                  Fetch 2 |                                 b
                        . |                               b
                        . |                             A
                        . |                             b
                        . |                           b
                        . |                               A
```

# Resume strategy

A process can be terminated, and then restarted. It may be desirable to jump from the starting point to the last valid point.

A process can be restart at a different time. It may be desirable to reattempt fetching contents that had previously failed.

Either way, a strategy that may be employed is to separate the contents that are about to be crawled and the content that have been crawled.

The input interval will be processed producing, a new interval with bounds that contains contents that haven't been crawled, and, the holes that exists within the contents that have been crawled.

It is then a business decision on what to do on these two separate contents. For the first scenario, we may ignore the holes. For the second scenario, we may ignore the new bounds. It is also possible to process both.

## Previous process

```
            @==>>             Terminate
... o o x | @ o o x x o o x o @   o o x o o x o x o o o x x x x ... x x # | x x ...
```

## Resume process

### Content

```
          [ o o o     o o   o o ]
```

### Holes

```
                [ x x     x ]
```

### Bound

```
... o o x   o o o x x o o x o o | @ o x o o x o x o o o x x x x ... x x # | x x ...
                                  @==>>
```
