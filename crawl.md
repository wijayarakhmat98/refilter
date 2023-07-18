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
