<?php
/*
Plugin Name: WPForms Lite to Database
*/

class Participant
{
    private $competition_id;
    private $name;
    private $gender;
    private $gear;
    private $age_category;
    private $club_or_city;
    private $email;
    private $send_confirmation;
    private $message;

    public function __construct($competition_id, $name, $gender, $gear, $age_category, $club_or_city, $email, $send_confirmation, $message)
    {
        $this->competition_id = $competition_id;
        $this->name = $name;
        $this->gender = $gender;
        $this->gear = $gear;
        $this->age_category = $age_category;
        $this->club_or_city = $club_or_city;
        $this->email = $email;
        $this->send_confirmation = $send_confirmation;
        $this->message = $message;
    }
}

class DbConnector
{
    /**
     * @var \PDO
     */
    private $pdo;
    public function __construct()
    {
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", DB_HOST, DB_NAME, DB_CHARSET);
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $opt);
    }

    public function query(string $sql, array $params)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function exec(string $sql, array $params)
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            // log exception or sth...
            die('Nie udało się zapisać zgłoszenia. Prosimy o kontakt mailowy slup.pomorze@gmail.com');
            return false;
        }
    }

    public function create_table()
    {
        $create_table_sql = file_get_contents('table.sql');
        $stmt = $this->pdo->prepare($create_table_sql);
        $stmt->execute();
    }
}

function show_registered_participants(string $text)
{
    $competition_id = get_competition_id($text);
    $html = '';
    if (!empty($competition_id) && is_numeric($competition_id)) {
        $participants = get_registered_participants($competition_id);
        if (empty($participants)) {
            $html = '<p>Jeszcze nikt się nie zarejestrował... na co czekasz? :)</p>';
        } else {
            $html = pretty_print_registered_participants($participants);
            $html .= pretty_print_count_by_categories($competition_id, count($participants));
        }
    }
    return $text . $html;
}

function pretty_print_registered_participants($participants): string
{
    $html = sprintf("<h4>Liczba zgłoszonych uczestników: %d</h4>", count($participants));
    $html .= "<table><thead><th>Lp.</th><th>Imię i nazwisko</th><th>Płeć</th><th>Klasa łuku</th><th>Kategoria wiekowa</th><th>Miasto/Klub</th></thead><tbody>";

    $index = 0;
    foreach ($participants as $participant_data) {
        $index++;
        $html .= "<tr><td>{$index}</td><td> {$participant_data['name']} </td><td> {$participant_data['gender']} </td><td> {$participant_data['gear']} </td><td> {$participant_data['age_category']} </td><td> {$participant_data['club_or_city']} </td></tr>";
    }
    $html .= "</tbody></table>";
    return $html;
}

function get_registered_participants(int $competition_id): array
{
    $dbConnector = new DbConnector();
    $participants = $dbConnector->query(
        'select * from wp_participant where competition_id = :competition_id',
        ['competition_id' => $competition_id]
    );

    return $participants;
}

function pretty_print_count_by_categories(int $competition_id, int $total): string
{
    $data = get_count_by_categories($competition_id);
    $html = "<h4>Liczba zgłoszonych uczestników w poszczególnych kategoriach:</h4>";
    $html .= "<table><tbody>";

    foreach ($data as $stats) {
        $html .= "<tr><td>{$stats->gear}</td><td>{$stats->number_of_participants}</td></tr>";
    }
    $html .= "<tr><td><b>Razem:</b></td><td><b>{$total}</b></td></tr>";
    $html .= "</tbody></table>";

    return $html;
}

function get_count_by_categories(int $competition_id): array
{
    global $wpdb;
    $sql = $wpdb->prepare('select gear, count(1) as number_of_participants from wp_participant where competition_id = %d group by gear', $competition_id);
    $result = $wpdb->get_results($sql);

    return $result;
}

function get_competition_id(string $post_content): string
{
    preg_match('|\[wpforms\s+id=\"(\d+)\"|', $post_content, $matches);
    $competition_id = $matches[1] ?? '';

    return $competition_id;
}

function save_to_db()
{
    $args = func_get_args();
    if (!is_array($args[1])) {
        throw new \Exception('Coś poszło nie tak, spróbuj ponownie później.');
    }
    $data = $args[1];
    $competition_id = $data['id'];

    $dbConnector = new DbConnector();
    $sql = 'INSERT INTO wp_participant (competition_id, name, gender, gear, age_category, club_or_city, email, send_confirmation, message)
      VALUES (:competition_id, :name, :gender, :gear, :age_category, :club_or_city, :email, :send_confirmation, :message)';

    $result = $dbConnector->exec($sql, [
        'competition_id' => $competition_id,
        'name' => join(' ', array_values($data['fields'][3])),
        'gender' => substr($data['fields'][12], 0, 1),
        'gear' => $data['fields'][4],
        'age_category' => $data['fields'][9],
        'club_or_city' => $data['fields'][5],
        'email' => $data['fields'][6],
        'send_confirmation' => (int)!empty($data['fields'][7]),
        'message' => $data['fields'][8],
    ]);

    return $result;

}

function send_email_confirmation() {
    $args = func_get_args();
    $data = $args[1]['fields'];

    $subject = $args[2]['settings']['form_title'] . ' - potwierdzenie';
    $to = get_option('admin_email');
    prepare_and_send_email($data, $to, $subject);

    if (!empty($data[7])) {
        $to = $data[6];
        prepare_and_send_email($data, $to, $subject);
    }
}

function prepare_and_send_email($data, $to, $subject)
{
    $message = '<p>Niniejszym potwierdzamy rejestrację na zawody na podane przez Ciebie dane:</p>';
    $message = '<ul>';
    $message .= sprintf('<li>Imię i nazwisko: %s </li>', join(' ', $data[3]));
    $message .= sprintf('<li>Płeć: %s </li>', $data[12]);
    $message .= sprintf('<li>Kategoria sprzętowa: %s </li>', $data[4]);
    $message .= sprintf('<li>Kategoria wiekowa: %s </li>', $data[9]);
    $message .= sprintf('<li>Miasto/Klub %s </li>', $data[5]);
    if (!empty($data[8])) {
        $message .= sprintf('<li>Wiadomość: %s </li>', $data[8]);
    }
    $message .= '</ul>';
    $message .= '<p>Do zobaczenia!</p><p></p><p>Stowarzyszenie Łuczników Pomorza "SŁuP"</p>';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($to, $subject, $message, $headers);
}

add_filter('the_content', 'show_registered_participants');
add_filter('the_excerpt', 'show_registered_participants');
add_action('wpforms_process_complete', 'save_to_db', 10, 4);
add_action('wpforms_process_complete', 'send_email_confirmation', 10, 4);