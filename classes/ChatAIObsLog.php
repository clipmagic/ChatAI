<?php namespace ProcessWire;

/**
 * Simple append-only logger for ChatAI events.
 *
 * Backed by chatai_obslog table.
 * Never throws out to callers.
 */
class ChatAIObsLog extends Wire
{
    const TABLE = 'chatai_obslog';

    /**
     * Log an event.
     *
     * @param string $eventType  e.g. "message", "cutoff", "blocked", "rate_limit", "error"
     * @param array  $data       keys: chat_id, status, model, meta
     */
    public function log(string $eventType, array $data = []): void
    {
        try {
            $db  = $this->wire('database');
            $now = date('Y-m-d H:i:s');

            $chatId = isset($data['chat_id']) ? (string) $data['chat_id'] : '';
            $status = isset($data['status']) ? (string) $data['status'] : 'ok';
            $model  = isset($data['model'])  ? (string) $data['model']  : '';
            $meta   = $data['meta'] ?? [];

            if(!is_string($meta)) {
                $json = json_encode(
                    $meta,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                $meta = $json !== false ? $json : '';
            }

            $sql = "
                INSERT INTO " . self::TABLE . "
                    (created, chat_id, event_type, status, model, meta)
                VALUES
                    (:created, :chat_id, :event_type, :status, :model, :meta)
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':created',    $now);
            $stmt->bindValue(':chat_id',    $chatId);
            $stmt->bindValue(':event_type', $eventType);
            $stmt->bindValue(':status',     $status);
            $stmt->bindValue(':model',      $model);
            $stmt->bindValue(':meta',       $meta);
            $stmt->execute();

        } catch(\Throwable $e) {
            // Never let logging break chat
            $this->wire('log')->save('chatai-obslog', $e->getMessage());
        }
    }

    /**
     * Fetch recent events for the Dashboard "event log" table.
     *
     * @return array[] list of rows
     */
    public function fetchRecent(int $limit = 100): array
    {
        $db    = $this->wire('database');
        $limit = max(1, min($limit, 500));

        $sql = "
            SELECT id, created, chat_id, event_type, status, model, meta
            FROM " . self::TABLE . "
            ORDER BY created DESC
            LIMIT :limit
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows ?: [];
        } catch(\Throwable $e) {
            $this->wire('log')->save('chatai-obslog', $e->getMessage());
            return [];
        }
    }

    /**
     * Simple summary for the "event types" chart.
     * Defaults to last 30 days.
     *
     * @return array event_type => count
     */
    public function fetchSummary(\DateTimeInterface $from = null): array
    {
        $db = $this->wire('database');

        if(!$from) {
            $from = new \DateTime('-30 days');
        }

        $sql = "
            SELECT event_type, COUNT(*) AS cnt
            FROM " . self::TABLE . "
            WHERE created >= :from
            GROUP BY event_type
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':from', $from->format('Y-m-d H:i:s'));

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e) {
            $this->wire('log')->save('chatai-obslog', $e->getMessage());
            return [];
        }

        $out = [];
        foreach($rows as $row) {
            $type = (string) $row['event_type'];
            $out[$type] = (int) $row['cnt'];
        }
        return $out;
    }

    public function fetchDailyVolume(int $days = 7): array
    {
        $db   = $this->wire('database');
        $days = max(1, min($days, 60)); // hard cap

        $from = new \DateTime("-{$days} days");
        $fromStr = $from->format('Y-m-d 00:00:00');

        $sql = "
        SELECT DATE(created) AS d, event_type, COUNT(*) AS cnt
        FROM " . self::TABLE . "
        WHERE created >= :from
          AND event_type IN ('reply','cutoff')
        GROUP BY d, event_type
        ORDER BY d ASC
    ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':from', $fromStr);

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->wire('log')->save('chatai-obslog', $e->getMessage());
            return [];
        }

        // Seed a full date range with zeros
        $byDate = [];
        $today  = new \DateTime(); // now
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (clone $today)->modify("-{$i} days")->format('Y-m-d');
            $byDate[$d] = [
                'date'  => $d,
                'total' => 0,
                // keep room for future series if needed
                'reply'  => 0,
                'cutoff' => 0,
            ];
        }

        foreach ($rows as $row) {
            $d     = (string) $row['d'];
            $etype = (string) $row['event_type'];
            $cnt   = (int) $row['cnt'];

            if (!isset($byDate[$d])) {
                $byDate[$d] = [
                    'date'  => $d,
                    'total' => 0,
                    'reply'  => 0,
                    'cutoff' => 0,
                ];
            }

            if ($etype === 'reply' || $etype === 'cutoff') {
                $byDate[$d][$etype] += $cnt;
                $byDate[$d]['total'] += $cnt;
            }
        }

        // Return in chronological order
        return array_values($byDate);
    }

    /**
     * Simple rule based recommendations based on a snapshot.
     * Returns plain text bullet items.
     */
    public function buildHeuristicInsights(array $snapshot): array
    {
        $out = [];

        if (!$snapshot) {
            return $out;
        }

        $totalEvents      = (int) ($snapshot['total_events'] ?? 0);
        $totalBlocked     = (int) ($snapshot['total_blocked'] ?? 0);
        $totalCutoffs     = (int) ($snapshot['total_cutoffs'] ?? 0);
        $successRate      = $snapshot['success_rate'] ?? null;         // 0–1 float
        $avgLatency       = $snapshot['avg_latency_ms'] ?? null;
        $uniqueBlockedIps = (int) ($snapshot['unique_blocked_ips'] ?? 0);
        $topErrors        = $snapshot['top_error_codes'] ?? [];
        $rangeDays        = (int) ($snapshot['range_days'] ?? 0);      // optional, for wording

        // KPI thresholds (with sane defaults)
        $successWarn = (int) ($snapshot['success_warn_pct'] ?? 85);    // warn below this %
        $latWarn     = (int) ($snapshot['latency_warn_ms']   ?? 5000); // "a bit slow"
        $latHigh     = (int) ($snapshot['latency_high_ms']   ?? 8000); // "too slow"

        // Helper label for the period
        $periodLabel = $rangeDays > 0
            ? sprintf($this->_('the last %d days'), $rangeDays)
            : $this->_('the selected period');

        if ($totalEvents === 0) {
            $out[] = $this->_('No recent chatbot activity. Embed the widget on key pages and test it yourself to confirm it is working.');
            return $out;
        }

        // --- Overall health / data volume ---
        if ($totalEvents < 10) {
            $out[] = sprintf(
                $this->_('Only %d events were logged in %s. Treat the charts and insights as early signals rather than stable trends.'),
                $totalEvents,
                $periodLabel
            );
        } elseif ($totalEvents < 50) {
            $out[] = sprintf(
                $this->_('%d events were logged in %s. Patterns are emerging, but a larger sample will give more reliable trends.'),
                $totalEvents,
                $periodLabel
            );
        } else {
            $out[] = sprintf(
                $this->_('%d events were logged in %s. This is enough traffic to rely on the trends shown in the charts.'),
                $totalEvents,
                $periodLabel
            );
        }

        // --- Blocked / blacklist activity ---
        if ($totalBlocked > 0) {
            if ($uniqueBlockedIps > 3) {
                $out[] = sprintf(
                    $this->_('%d requests were blocked from %d different IP addresses in %s. Review your blacklist terms to ensure they are not too aggressive.'),
                    $totalBlocked,
                    $uniqueBlockedIps,
                    $periodLabel
                );
            } else {
                $out[] = sprintf(
                    $this->_('%d requests were blocked in %s. Check the blacklist terms and confirm they match the behaviour you want to prevent.'),
                    $totalBlocked,
                    $periodLabel
                );
            }
        }

        // --- Cutoffs (truncation) ---
        if ($totalCutoffs > 0) {
            $out[] = sprintf(
                $this->_('There were %d truncated replies in %s. Consider increasing the max tokens or trimming the system prompt so responses fit comfortably within the limit.'),
                $totalCutoffs,
                $periodLabel
            );
        }

        // --- Success rate vs KPI ---
        if ($successRate !== null && $totalEvents >= 10) {
            $pct = (int) round($successRate * 100);
            $lowThreshold = max(0, $successWarn - 10);

            if ($pct < $lowThreshold) {
                // Clearly below target
                $out[] = sprintf(
                    $this->_('The success rate is about %d%% in %s, which is below your target of %d%%. Review failed and blocked events in the CSV export to see what users are asking for.'),
                    $pct,
                    $periodLabel,
                    $successWarn
                );
            } elseif ($pct < $successWarn) {
                // Borderline
                $out[] = sprintf(
                    $this->_('The success rate is about %d%% in %s, slightly below your target of %d%%. Keep an eye on repeated errors and consider tightening prompts for common failure cases.'),
                    $pct,
                    $periodLabel,
                    $successWarn
                );
            } elseif ($pct >= min($successWarn + 10, 99)) {
                // Comfortably above target
                $out[] = sprintf(
                    $this->_('The success rate is about %d%% in %s, above your configured target. You can focus on fine tuning prompt wording and canned responses to improve answer quality.'),
                    $pct,
                    $periodLabel
                );
            }
        }

        // --- Latency vs KPI ---
        if ($avgLatency !== null && $avgLatency > 0) {
            if ($avgLatency > $latHigh) {
                $out[] = sprintf(
                    $this->_('Average response time is around %d ms in %s, which is above your high-latency threshold. Users are likely to perceive the bot as slow. Consider a smaller or faster model, a shorter prompt, or checking network latency.'),
                    (int) $avgLatency,
                    $periodLabel
                );
            } elseif ($avgLatency > $latWarn) {
                $out[] = sprintf(
                    $this->_('Average response time is around %d ms in %s, above your warning threshold. If possible, trim unnecessary context and confirm the server and network have enough capacity.'),
                    (int) $avgLatency,
                    $periodLabel
                );
            } else {
                // Only bother praising latency if you also have a reasonable number of events
                if ($totalEvents >= 20) {
                    $out[] = sprintf(
                        $this->_('Average response time is around %d ms in %s, within your configured latency thresholds.'),
                        (int) $avgLatency,
                        $periodLabel
                    );
                }
            }
        }

        // --- Top error codes ---
        if (!empty($topErrors)) {
            $topCode  = array_key_first($topErrors);
            $topCount = (int) $topErrors[$topCode];

            if ($topCode === 'rate_limit' || $topCode === 'rate_limit_exceeded' || $topCode === 'insufficient_quota') {
                $out[] = $this->_('Rate limit or quota errors are appearing. Consider lowering per-user limits, upgrading the OpenAI plan, or caching answers to common questions.');
            } elseif ($topCode === 'timeout') {
                $out[] = $this->_('Timeout errors are occurring. Check the server network and any reverse proxies such as Cloudflare for timeouts or dropped connections.');
            } elseif ($topCount > 0) {
                $out[] = sprintf(
                    $this->_('The most frequent error code is “%s”. Review those events in the CSV export and decide how you want the chatbot to handle them.'),
                    $topCode
                );
            }
        }

        // --- Generic positive suggestion if everything looks smooth ---
        if (
            $totalEvents > 30 &&
            $totalBlocked === 0 &&
            $totalCutoffs === 0 &&
            empty($topErrors)
        ) {
            $out[] = $this->_('The chatbot is running smoothly. Use the event log or CSV export to spot repeated questions and consider adding custom canned responses or FAQ pages.');
        }

        return $out;
    }


    /**
     * Compact numeric snapshot for "insights" and dashboard.
     * Only looks at the last N days of data.
     */
    public function buildInsightsSnapshot(int $days = 30): array
    {
        $db   = $this->wire('database');
        $days = max(1, min($days, 60));

        $from = new \DateTime("-{$days} days");
        $fromStr = $from->format('Y-m-d 00:00:00');

        $sql = "
        SELECT created, chat_id, event_type, status, model, meta
        FROM " . self::TABLE . "
        WHERE created >= :from
        ORDER BY created DESC
    ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':from', $fromStr);

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->wire('log')->save('chatai-obslog', $e->getMessage());
            return [];
        }

        $totalEvents   = 0;
        $totalSuccess  = 0;
        $totalFailed   = 0;
        $totalReplies  = 0;
        $totalCutoffs  = 0;
        $totalBlocked  = 0;
        $sumLatency    = 0;
        $latencyCount  = 0;
        $rateLimited   = 0;

        $chatIds       = [];
        $blockedIps    = [];
        $topErrorCodes = [];

        foreach ($rows as $row) {
            $totalEvents++;

            $eventType = (string) $row['event_type'];
            $status    = (string) $row['status'];
            $chatId    = (string) $row['chat_id'];

            if ($status === 'ok') {
                $totalSuccess++;
            } else {
                $totalFailed++;
            }

            if ($chatId !== '') {
                $chatIds[$chatId] = true;
            }

            if ($eventType === 'reply') {
                $totalReplies++;
            } elseif ($eventType === 'cutoff') {
                $totalCutoffs++;
            } elseif ($eventType === 'blocked') {
                $totalBlocked++;
            }

            $meta = [];
            if (!empty($row['meta'])) {
                $decoded = json_decode($row['meta'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            if (isset($meta['latency_ms']) && is_numeric($meta['latency_ms'])) {
                $sumLatency   += (int) $meta['latency_ms'];
                $latencyCount++;
            }

            if (isset($meta['err_code']) && $meta['err_code'] !== null && $meta['err_code'] !== '') {
                $code = (string) $meta['err_code'];
                $topErrorCodes[$code] = ($topErrorCodes[$code] ?? 0) + 1;

                if (in_array($code, ['rate_limit', 'rate_limit_exceeded', 'throttled'], true)) {
                    $rateLimited++;
                }
            }

            if ($eventType === 'blocked' && isset($meta['ip_hash']) && $meta['ip_hash'] !== '') {
                $blockedIps[(string) $meta['ip_hash']] = true;
            }
        }


        $totalMessages = $totalReplies + $totalCutoffs;
        $totalChats    = count($chatIds);
        $avgLatency    = $latencyCount > 0 ? (int) round($sumLatency / $latencyCount) : null;
        $successRate   = $totalEvents > 0 ? ($totalSuccess / $totalEvents) : null;

        arsort($topErrorCodes);

        return [
            'period_days'        => $days,
            'total_events'       => $totalEvents,
            'total_success'      => $totalSuccess,
            'total_failed'       => $totalFailed,
            'total_replies'      => $totalReplies,
            'total_cutoffs'      => $totalCutoffs,
            'total_blocked'      => $totalBlocked,
            'total_messages'     => $totalMessages,
            'total_chats'        => $totalChats,
            'avg_latency_ms'     => $avgLatency,
            'success_rate'       => $successRate,
            'unique_blocked_ips' => count($blockedIps),
            'top_error_codes'    => $topErrorCodes,
            'rate_limited'       => $rateLimited,
            'event_summary' => [
                'reply'      => $totalReplies,
                'cutoff'     => $totalCutoffs,
                'blocked'    => $totalBlocked,
                'rate_limit' => $rateLimited,
            ],

        ];
    }

    public function fetchRange(\DateTimeInterface $from, \DateTimeInterface $to): array {
        $db = $this->wire('database');

        $sql = "
            SELECT id, created, chat_id, event_type, status, model, meta
            FROM " . self::TABLE . "
            WHERE created >= :from
              AND created <= :to
            ORDER BY created ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':from', $from->format('Y-m-d H:i:s'));
        $stmt->bindValue(':to',   $to->format('Y-m-d H:i:s'));

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\Throwable $e) {
            $this->wire('log')->save('chatai-obslog', $e->getMessage());
            return [];
        }
    }

}
