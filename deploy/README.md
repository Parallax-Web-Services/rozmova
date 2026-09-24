# Self-hosted inference

Two services behind the `SpeechToText` and `Translator` contracts. The PHP side
talks to them over HTTP and does not care what is inside.

```bash
docker compose up -d --build
```

Then point the drivers at them:

```php
$asr = new HttpSpeechToText(new HttpTransport('http://asr:8080'));
$mt  = new HttpTranslator(new HttpTransport('http://mt:8080'));
```

## Sizing

An archive is batch work. A handful of speeches a day is nothing like a live
stream, so this does not need a GPU — which on DigitalOcean is the difference
between tens of dollars a month and hundreds.

| Droplet | Fits |
|---|---|
| 4 GB / 2 vCPU | MT alone, or ASR with a small model |
| 8 GB / 4 vCPU | both services, `large-v3` int8, comfortable for daily batches |
| 16 GB / 8 vCPU | headroom for backfilling an archive in bulk |

First boot downloads model weights — several GB, and several minutes. The
healthchecks allow for it with a long `start_period`. Weights live in a named
volume so a rebuild does not re-download them.

## Neither service is exposed

Both use `expose`, not `ports`. They are reachable on the compose network and
nowhere else. If you split them onto their own droplet, put them on a VPC and
keep them off the public interface — an open ASR endpoint is free transcription
for whoever finds it, and an open MT endpoint is a free translation API.

## Engine choice

The ASR service defaults to faster-whisper because it stands up in minutes.
That is a starting point, not the accuracy answer for Ukrainian:

| Model | Ukrainian WER (Common Voice) |
|---|---|
| `theodotus/stt_ua_fastconformer_hybrid_large_pc` | ~4% |
| `nvidia/stt_uk_citrinet_1024_gamma_0_25` | 4.32% |
| Whisper family | well behind, and 1.55B params against ~115M |

Moving to a NeMo Conformer means rewriting `deploy/asr/app.py` and changing the
`engine` string it reports. Nothing in PHP changes — that is the entire point of
the interface, and the reason not to have forked Whisper.

Benchmark on your own audio before switching. Common Voice is clean read speech
and a poor proxy for podium audio with crowd noise.
