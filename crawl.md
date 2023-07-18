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
