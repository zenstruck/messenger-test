# CHANGELOG

## [v1.9.1](https://github.com/zenstruck/messenger-test/releases/tag/v1.9.1)

November 23rd, 2023 - [v1.9.0...v1.9.1](https://github.com/zenstruck/messenger-test/compare/v1.9.0...v1.9.1)

* baf5893 fix: set support_delay_stamp to false by default (#69) by @nikophil

## [v1.9.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.9.0)

October 24th, 2023 - [v1.8.0...v1.9.0](https://github.com/zenstruck/messenger-test/compare/v1.8.0...v1.9.0)

* 0554e7c feat: Symfony 7 support (#67) by @kbond

## [v1.8.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.8.0)

October 18th, 2023 - [v1.7.3...v1.8.0](https://github.com/zenstruck/messenger-test/compare/v1.7.3...v1.8.0)

* 71d66ed feat: support delay stamp and require php 8.1 (#66) by @nikophil, @kbond

## [v1.7.3](https://github.com/zenstruck/messenger-test/releases/tag/v1.7.3)

October 9th, 2023 - [v1.7.2...v1.7.3](https://github.com/zenstruck/messenger-test/compare/v1.7.2...v1.7.3)

* 68b535a minor: Add conditional return type for `EnvelopeCollection::messages` (#64) by @norkunas

## [v1.7.2](https://github.com/zenstruck/messenger-test/releases/tag/v1.7.2)

February 24th, 2023 - [v1.7.1...v1.7.2](https://github.com/zenstruck/messenger-test/compare/v1.7.1...v1.7.2)

* 2e51d27 fix: always collect messages in TestTransport::$queue (#62) by @nikophil

## [v1.7.1](https://github.com/zenstruck/messenger-test/releases/tag/v1.7.1)

February 24th, 2023 - [v1.7.0...v1.7.1](https://github.com/zenstruck/messenger-test/compare/v1.7.0...v1.7.1)

* 6a179b6 fix(EnvelopeCollection): return type for psalm and method final (#60) by @flohw

## [v1.7.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.7.0)

February 22nd, 2023 - [v1.6.1...v1.7.0](https://github.com/zenstruck/messenger-test/compare/v1.6.1...v1.7.0)

* 61e8802 feat: Make bus queriable (#54) by @flohw
* 823f155 fix(ci): prevent running fixcs/sync-with-template on forks (#57) by @kbond
* bfa2191 fix(tests): phpunit deprecation (#57) by @kbond

## [v1.6.1](https://github.com/zenstruck/messenger-test/releases/tag/v1.6.1)

February 2nd, 2023 - [v1.6.0...v1.6.1](https://github.com/zenstruck/messenger-test/compare/v1.6.0...v1.6.1)

* 12c8b18 fix: reinit TestTransport before each test (#56) by @nikophil

## [v1.6.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.6.0)

January 23rd, 2023 - [v1.5.1...v1.6.0](https://github.com/zenstruck/messenger-test/compare/v1.5.1...v1.6.0)

* fb0bb0c feat: enable messages collection (#55) by @nikophil
* 7fb455d fix(ci): add token by @kbond
* 508f890 chore(ci): fix by @kbond
* 416f086 fix(test): deprecation (#53) by @kbond
* 885fbd8 ci: adjust (#53) by @kbond

## [v1.5.1](https://github.com/zenstruck/messenger-test/releases/tag/v1.5.1)

September 23rd, 2022 - [v1.5.0...v1.5.1](https://github.com/zenstruck/messenger-test/compare/v1.5.0...v1.5.1)

* 56f667c [bug] ignore receiver detached from transport (#52) by @alli83

## [v1.5.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.5.0)

September 6th, 2022 - [v1.4.2...v1.5.0](https://github.com/zenstruck/messenger-test/compare/v1.4.2...v1.5.0)

* 0fe8aef [feature] allow `TestTransport::send()` to accept `object|array` (#50) by @kbond

## [v1.4.2](https://github.com/zenstruck/messenger-test/releases/tag/v1.4.2)

June 15th, 2022 - [v1.4.1...v1.4.2](https://github.com/zenstruck/messenger-test/compare/v1.4.1...v1.4.2)

* 4b4566d [bug] fix processing empty queue (#48) by @kbond
* 6f79b43 [doc] add troubleshooting section and detached entities workaround (#46) by @kbond
* 8efb392 [doc] update config to new `when@test` notation (#46) by @kbond
* 8253ec9 [minor] simplify ci config (#45) by @kbond
* ea298ec [minor] remove scrutinizer (#45) by @kbond
* 2a1a944 [doc] fix: typo in README.md (#42) by @romainallanot

## [v1.4.1](https://github.com/zenstruck/messenger-test/releases/tag/v1.4.1)

March 4th, 2022 - [v1.4.0...v1.4.1](https://github.com/zenstruck/messenger-test/compare/v1.4.0...v1.4.1)

* a70c269 [bug] reset transport before tests (#40) by @rodnaph
* bb97995 [minor] add conflict for symfony/framework-bundle 5.4.5 & 6.0.5 (#39) by @kbond
* 5a5e40c [minor] fix typehint by @kbond
* 5932352 [minor] add static code analysis with phpstan (#38) by @kbond

## [v1.4.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.4.0)

January 8th, 2022 - [v1.3.0...v1.4.0](https://github.com/zenstruck/messenger-test/compare/v1.3.0...v1.4.0)

* 8a434ad [feature] add ability to enable retries (#37) by @kbond
* 1381f27 [bug] disable retries (#37) by @kbond

## [v1.3.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.3.0)

December 30th, 2021 - [v1.2.1...v1.3.0](https://github.com/zenstruck/messenger-test/compare/v1.2.1...v1.3.0)

* 13376bb [minor] give a name to the transport in the Worker (#34) by @nikophil
* 97fb25c [feature] add test_serialization option in dsn (#35) by @nikophil
* cf2ec7f [bug] prevent infinite loop in unblock mode (#36) by @nikophil

## [v1.2.1](https://github.com/zenstruck/messenger-test/releases/tag/v1.2.1)

October 19th, 2021 - [v1.2.0...v1.2.1](https://github.com/zenstruck/messenger-test/compare/v1.2.0...v1.2.1)

* 8aac8d0 [minor] allow "0" for EnvelopeCollection::assertContains() (#30) by @kbond

## [v1.2.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.2.0)

October 7th, 2021 - [v1.1.0...v1.2.0](https://github.com/zenstruck/messenger-test/compare/v1.1.0...v1.2.0)

* b02e861 [feature] add EnvelopeCollection::back() (#28) by @kbond
* d649463 [ci] use reusable workflows (#27) by @kbond

## [v1.1.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.1.0)

October 5th, 2021 - [v1.0.0...v1.1.0](https://github.com/zenstruck/messenger-test/compare/v1.0.0...v1.1.0)

* e54feb3 [feature] process messages with app's event dispatcher (#26) by @kbond
* 9867e39 [minor] refactor service definitions (#25) by @kbond
* 1499a57 [minor] add .editorconfig (#25) by @kbond
* 84442ac [minor] test on Symfony 5.4 (#22) by @kbond

## [v1.0.0](https://github.com/zenstruck/messenger-test/releases/tag/v1.0.0)

September 28th, 2021 - _[Initial Release](https://github.com/zenstruck/messenger-test/commits/v1.0.0)_
