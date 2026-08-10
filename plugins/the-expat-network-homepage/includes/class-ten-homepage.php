<?php
/**
 * Front-end homepage renderer.
 *
 * @package TheExpatNetworkHomepage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEN_Homepage {
	/** @var TEN_Settings */
	private $settings;

	/** @var TEN_Form_Handler */
	private $forms;

	/**
	 * Constructor.
	 *
	 * @param TEN_Settings     $settings Settings service.
	 * @param TEN_Form_Handler $forms Form service.
	 */
	public function __construct( TEN_Settings $settings, TEN_Form_Handler $forms ) {
		$this->settings = $settings;
		$this->forms    = $forms;
	}

	/** Register hooks. */
	public function init() {
		add_shortcode( 'ten_homepage', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** Enqueue assets only when the shortcode is present. */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'ten_homepage' ) ) {
			return;
		}

		$css_file = TEN_HOMEPAGE_DIR . 'assets/css/homepage.css';
		$js_file  = TEN_HOMEPAGE_DIR . 'assets/js/homepage.js';

		wp_enqueue_style(
			'ten-homepage',
			TEN_HOMEPAGE_URL . 'assets/css/homepage.css',
			array(),
			file_exists( $css_file ) ? (string) filemtime( $css_file ) : TEN_HOMEPAGE_VERSION
		);

		wp_enqueue_script(
			'ten-homepage',
			TEN_HOMEPAGE_URL . 'assets/js/homepage.js',
			array(),
			file_exists( $js_file ) ? (string) filemtime( $js_file ) : TEN_HOMEPAGE_VERSION,
			true
		);
	}

	/** Render shortcode. */
	public function render_shortcode() {
		// Public forms contain fresh WordPress nonces and must not be served from a stale full-page cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( has_action( 'litespeed_control_set_nocache' ) ) {
			do_action( 'litespeed_control_set_nocache', 'The Expat Network native forms require fresh nonces.' );
		}

		$contact_email    = sanitize_email( (string) $this->settings->get( 'contact_email' ) );
		$privacy_fallback = get_privacy_policy_url();
		$privacy_url      = $this->settings->get_page_url( 'privacy_page_id', $privacy_fallback ? $privacy_fallback : home_url( '/privacy-policy/' ) );
		$imprint_url      = $this->settings->get_page_url( 'imprint_page_id', home_url( '/impressum/' ) );
		$tools_url        = apply_filters( 'ten_homepage_tools_url', 'https://theexpatnetwork.de/' );

		ob_start();
		?>
		<div class="ten-homepage">
			<?php $this->render_header(); ?>
			<?php $this->render_hero( $tools_url ); ?>
			<?php $this->render_situation_gateways(); ?>
			<?php $this->render_pathways(); ?>
			<?php $this->render_market_reality(); ?>
			<?php $this->render_germany_tools( $tools_url ); ?>
			<?php $this->render_official_sources(); ?>
			<?php $this->render_india_germany(); ?>
			<?php $this->render_organisations(); ?>
			<?php $this->render_founder_trust( $contact_email ); ?>
			<?php $this->render_join_network( $privacy_url, $imprint_url ); ?>
			<?php $this->render_organisation_enquiry( $privacy_url ); ?>
			<?php $this->render_faq(); ?>
			<?php $this->render_footer( $privacy_url, $imprint_url ); ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** 1. Header */
	private function render_header() {
		?>
		<header class="ten-header" role="banner">
			<div class="ten-container ten-header__inner">
				<div class="ten-header__brand">
					<span class="ten-header__title">The Expat Network</span>
					<span class="ten-header__badge">theexpatnetwork.org</span>
				</div>
				<nav class="ten-header__nav" aria-label="Main Navigation">
					<a href="/pathways/">Paths</a>
					<a href="https://theexpatnetwork.de/" target="_blank" rel="noopener noreferrer">Germany Tools</a>
					<a href="/india-germany/">India &harr; Germany</a>
					<a href="/for-organisations/">For Organisations</a>
					<a href="/about/">About</a>
				</nav>
				<a class="ten-header__path-cta" href="#start-with-your-situation">Find Your Germany Path</a>
			</div>
		</header>
		<?php
	}

	/** 2. Hero */
	private function render_hero( $tools_url ) {
		?>
		<section class="ten-section ten-hero" aria-labelledby="ten-hero-title">
			<div class="ten-container ten-hero__inner">
				<div class="ten-hero__content" data-ten-reveal>
					<p class="ten-eyebrow">Germany Orientation &amp; Pathway Orchestration</p>
					<h1 id="ten-hero-title">Understand Your Path to Working and Living in Germany</h1>
					<p class="ten-lead">The Expat Network helps international talent and organisations navigate the journey to Germany — connecting structured pathway guidance, practical decision-support tools, official sources, and qualified specialists.</p>
					<div class="ten-actions">
						<a class="ten-button ten-button--primary" href="#start-with-your-situation">Find Your Germany Path</a>
						<a class="ten-button ten-button--secondary" href="<?php echo esc_url( $tools_url ); ?>" target="_blank" rel="noopener noreferrer">Explore Germany Tools <span aria-hidden="true">↗</span></a>
					</div>
					<p class="ten-microcopy">Independent &middot; Official-source first &middot; Clear domain boundaries</p>
				</div>
			</div>
		</section>
		<?php
	}

	/** 3. Start With Your Situation */
	private function render_situation_gateways() {
		$gateways = array(
			array(
				'title' => 'I want to work in Germany',
				'desc'  => 'Understand the realistic sequence for preparing, finding work and navigating the Germany pathway.',
				'url'   => '/work-in-germany/',
				'label' => 'Explore the Work in Germany Path',
			),
			array(
				'title' => 'I have a German job offer',
				'desc'  => 'Understand what to review now and which official or practical next steps may apply.',
				'url'   => '/german-job-offer/',
				'label' => 'Check Your Next Steps',
			),
			array(
				'title' => 'I already live in Germany',
				'desc'  => 'Navigate work, progression and practical next steps from inside Germany.',
				'url'   => '/living-in-germany/',
				'label' => 'Navigate Your Next Step',
			),
			array(
				'title' => 'I represent an organisation',
				'desc'  => 'Explore TEN routes for organisations, programmes, ecosystem activity and cooperation enquiries.',
				'url'   => '/for-organisations/',
				'label' => 'Explore Organisation Routes',
			),
		);
		?>
		<section id="start-with-your-situation" class="ten-section ten-anchor-section ten-section--sand" aria-labelledby="ten-situations-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">Orientation &amp; Routing</p>
					<h2 id="ten-situations-title">Start With Your Situation</h2>
					<p>Choose the starting point that best matches where you are now. Each route leads to a dedicated .org pathway rather than a form or eligibility decision.</p>
				</div>
				<div class="ten-grid ten-grid--2 ten-gateways">
					<?php foreach ( $gateways as $gateway ) : ?>
						<article class="ten-card ten-gateway-card" data-ten-reveal>
							<h3><?php echo esc_html( $gateway['title'] ); ?></h3>
							<p><?php echo esc_html( $gateway['desc'] ); ?></p>
							<a class="ten-card-link" href="<?php echo esc_url( $gateway['url'] ); ?>"><?php echo esc_html( $gateway['label'] ); ?> <span aria-hidden="true">&rarr;</span></a>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/** 4. Germany Pathways */
	private function render_pathways() {
		$cards = array(
			array(
				'title' => 'Understand',
				'desc'  => 'Clarify your situation, profession and realistic Germany route.',
			),
			array(
				'title' => 'Prepare',
				'desc'  => 'Address language, documents, recognition and practical readiness where relevant.',
			),
			array(
				'title' => 'Act',
				'desc'  => 'Use appropriate TEN tools, official programmes or specialist support.',
			),
			array(
				'title' => 'Continue',
				'desc'  => 'Move into work, settlement, progression or your next Germany stage.',
			),
		);
		?>
		<section id="pathways" class="ten-section ten-anchor-section" aria-labelledby="ten-pathways-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">Structured Guidance</p>
					<h2 id="ten-pathways-title">Germany Pathways</h2>
					<p>TEN organises the journey into a clear sequence, then routes you to the right information, tool, official source or qualified specialist.</p>
				</div>
				<div class="ten-grid ten-grid--4">
					<?php foreach ( $cards as $card ) : ?>
						<article class="ten-card" data-ten-reveal>
							<h3><?php echo esc_html( $card['title'] ); ?></h3>
							<p><?php echo esc_html( $card['desc'] ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="ten-actions ten-actions--section" data-ten-reveal>
					<a class="ten-button ten-button--secondary" href="/pathways/">Explore Germany Pathways &rarr;</a>
				</div>
			</div>
		</section>
		<?php
	}

	/** 5. Profession & Market Reality */
	private function render_market_reality() {
		$pillars = array(
			array(
				'title' => 'Profession',
				'desc'  => 'Different professions have different qualification, recognition, language and market expectations.',
			),
			array(
				'title' => 'Language',
				'desc'  => 'The level of German that matters depends on the profession, workplace and stage of the journey.',
			),
			array(
				'title' => 'Offer & Living Reality',
				'desc'  => 'Salary, location, employment conditions and living costs should be considered together before making major decisions.',
			),
		);
		?>
		<section id="market-reality" class="ten-section ten-section--sage ten-anchor-section" aria-labelledby="ten-reality-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">Practical Reality</p>
					<h2 id="ten-reality-title">Profession &amp; Market Reality</h2>
					<p>Germany pathways depend on more than a single rule. Profession, language, employment conditions and personal circumstances all shape what should be checked next.</p>
				</div>
				<div class="ten-grid ten-grid--3">
					<?php foreach ( $pillars as $pillar ) : ?>
						<article class="ten-card ten-card--soft" data-ten-reveal>
							<h3><?php echo esc_html( $pillar['title'] ); ?></h3>
							<p><?php echo esc_html( $pillar['desc'] ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
				<p class="ten-disclaimer" data-ten-reveal>TEN helps users understand what to check. Official authorities and qualified specialists determine matters within their competence.</p>
			</div>
		</section>
		<?php
	}

	/** 6. Germany Tools */
	private function render_germany_tools( $tools_url ) {
		?>
		<section id="germany-tools" class="ten-section ten-section--sand ten-anchor-section" aria-labelledby="ten-tools-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">Check &middot; Assess &middot; Calculate &middot; Prepare</p>
					<h2 id="ten-tools-title">Germany Tools</h2>
					<p><strong>.org</strong> helps you understand and navigate the journey. <strong>.de</strong> provides focused checkers, assessments, calculators and preparation tools.</p>
				</div>
				<div class="ten-tools-banner" data-ten-reveal>
					<div class="ten-tools-banner__content">
						<h3>Use a Germany Tool When a Practical Check Helps</h3>
						<p>Move to the tools domain when you need a focused self-assessment or preparation step, then return to the wider journey on .org.</p>
						<a class="ten-button ten-button--primary" href="<?php echo esc_url( $tools_url ); ?>" target="_blank" rel="noopener noreferrer">Explore Germany Tools <span aria-hidden="true">↗</span></a>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/** 7. Official Sources */
	private function render_official_sources() {
		?>
		<section id="official-sources" class="ten-section ten-anchor-section" aria-labelledby="ten-official-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">Official-Source First</p>
					<h2 id="ten-official-title">Official Sources</h2>
					<p>For immigration, residence, recognition and other official decisions, TEN explains the context and routes users towards the competent official source.</p>
					<p class="ten-disclaimer">Official authorities — not TEN — make official determinations.</p>
				</div>
				<div class="ten-actions ten-actions--section" data-ten-reveal>
					<a class="ten-button ten-button--secondary" href="/official-sources/">Explore Official Sources &rarr;</a>
				</div>
			</div>
		</section>
		<?php
	}

	/** 8. India ↔ Germany */
	private function render_india_germany() {
		?>
		<section id="india-germany" class="ten-section ten-section--sage ten-anchor-section" aria-labelledby="ten-ig-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">India &harr; Germany Corridor</p>
					<h2 id="ten-ig-title">India &harr; Germany</h2>
					<p>India &harr; Germany is an important corridor within TEN, not the limit of the network. This section brings together practical context around mobility, professional pathways, research and innovation, and relevant ecosystem developments.</p>
				</div>
				<div class="ten-grid ten-grid--2" data-ten-reveal>
					<div class="ten-card">
						<h3>Mobility &amp; Professional Pathways</h3>
						<p>Understand the practical sequence and source landscape for people exploring Germany from India or navigating the corridor from within Germany.</p>
					</div>
					<div class="ten-card">
						<h3>Research, Innovation &amp; Ecosystem Context</h3>
						<p>Discover relevant developments, programmes and ecosystem information without implying institutional affiliation or endorsement.</p>
					</div>
				</div>
				<div class="ten-actions ten-actions--section" data-ten-reveal>
					<a class="ten-button ten-button--secondary" href="/india-germany/">Explore India &harr; Germany &rarr;</a>
				</div>
			</div>
		</section>
		<?php
	}

	/** 9. For Organisations */
	private function render_organisations() {
		?>
		<section id="for-organisations" class="ten-section ten-anchor-section" aria-labelledby="ten-orgs-title">
			<div class="ten-container">
				<div class="ten-section-heading" data-ten-reveal>
					<p class="ten-eyebrow">For Organisations</p>
					<h2 id="ten-orgs-title">For Organisations</h2>
					<p>For organisations exploring international talent pathways, programme visibility, ecosystem initiatives, user insight, specialist or resource routing, or potential cooperation with TEN.</p>
				</div>
				<div class="ten-grid ten-grid--3" data-ten-reveal>
					<article class="ten-card">
						<h3>Employers &amp; Organisations</h3>
						<p>Understand relevant international-talent pathways and practical user needs.</p>
					</article>
					<article class="ten-card">
						<h3>Ecosystem &amp; Programme Actors</h3>
						<p>Explore programme visibility, ecosystem information and cooperation discussions.</p>
					</article>
					<article class="ten-card">
						<h3>Qualified Specialists</h3>
						<p>Discuss appropriate specialist routing where authorised or expert fulfilment is required.</p>
					</article>
				</div>
				<div class="ten-actions ten-actions--section" data-ten-reveal>
					<a class="ten-button ten-button--primary" href="/for-organisations/">Explore Organisation Routes</a>
					<a class="ten-button ten-button--secondary" href="#partner-with-us">Discuss an Organisation or Cooperation Enquiry</a>
				</div>
			</div>
		</section>
		<?php
	}

	/** 10. Founder & Trust */
	private function render_founder_trust( $contact_email ) {
		$email_link = $contact_email ? 'mailto:' . $contact_email : '';
		?>
		<section id="about" class="ten-section ten-about ten-anchor-section ten-section--sand" aria-labelledby="ten-about-title">
			<div class="ten-container ten-about__grid">
				<div data-ten-reveal>
					<p class="ten-eyebrow">Founder-Led &amp; Independent</p>
					<h2 id="ten-about-title">Founder &amp; Trust</h2>
				</div>
				<div class="ten-about__copy" data-ten-reveal>
					<p><strong>Gagan Grover</strong><br>Founder, The Expat Network</p>
					<p>The Expat Network is independently operated as an orientation and pathway orchestration platform. We maintain strict ethical and operational boundaries:</p>
					<ul>
						<li><strong>Independent Operation:</strong> Not a government agency, embassy, or consulate.</li>
						<li><strong>Human Review:</strong> Submissions are reviewed by people, not automated job-matching algorithms.</li>
						<li><strong>Official-Source First:</strong> We route to official government authorities for legal determinations.</li>
						<li><strong>No Guaranteed Outcomes:</strong> We do not guarantee visa approvals, jobs, or relocation outcomes.</li>
						<li><strong>Privacy-Conscious:</strong> Minimal data collection with automatic retention deadlines.</li>
					</ul>
					<?php if ( $email_link ) : ?>
						<p><a class="ten-about__email" href="<?php echo esc_url( $email_link ); ?>"><?php echo esc_html( antispambot( $contact_email ) ); ?></a></p>
					<?php endif; ?>
					<p><a class="ten-card-link" href="/about/">Learn More About Our Philosophy &rarr;</a></p>
				</div>
			</div>
		</section>
		<?php
	}

	/** 11. Join The Network */
	private function render_join_network( $privacy_url, $imprint_url ) {
		?>
		<section id="join" class="ten-section ten-section--form ten-anchor-section" aria-labelledby="ten-join-title">
			<div class="ten-container ten-form-shell">
				<div class="ten-section-heading ten-section-heading--center" data-ten-reveal>
					<p class="ten-eyebrow">Continue With TEN</p>
					<h2 id="ten-join-title">Join The Network</h2>
					<p>If you want to maintain a connection with TEN after exploring your pathway, you can register your professional background and current interests for human review.</p>
					<p class="ten-disclaimer">Registration is not a job application, visa assessment or guarantee of an introduction, opportunity or response.</p>
				</div>
				<div class="ten-form-card" data-ten-reveal>
					<?php echo $this->forms->render_candidate_form( $privacy_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<p class="ten-form-privacy">Please do not submit identification documents, residence permits, financial information, health information or other unnecessary sensitive documents through this form. <a href="<?php echo esc_url( $privacy_url ); ?>">Privacy Policy</a> <span aria-hidden="true">&middot;</span> <a href="<?php echo esc_url( $imprint_url ); ?>">Imprint</a></p>
			</div>
		</section>
		<?php
	}

	/** 12. Organisation / Cooperation Enquiry */
	private function render_organisation_enquiry( $privacy_url ) {
		?>
		<section id="partner-with-us" class="ten-section ten-section--partners ten-anchor-section" aria-labelledby="ten-partners-title">
			<div class="ten-container ten-form-shell">
				<div class="ten-section-heading ten-section-heading--center" data-ten-reveal>
					<p class="ten-eyebrow">Organisation Enquiries</p>
					<h2 id="ten-partners-title">Organisation / Cooperation Enquiry</h2>
					<p>Discuss an organisation, programme, ecosystem, specialist-routing or cooperation enquiry with TEN.</p>
					<p class="ten-disclaimer">Submitting an enquiry does not create a partnership, endorsement, commercial relationship or obligation on either side.</p>
				</div>
				<div class="ten-form-card" data-ten-reveal>
					<?php echo $this->forms->render_partner_form( $privacy_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</section>
		<?php
	}

	/** 13. FAQ */
	private function render_faq() {
		$items = array(
			array(
				'question' => 'What does The Expat Network do?',
				'answer'   => 'The Expat Network (.org) provides orientation, pathway explanation, official-source routing and structured next-step navigation for people and organisations navigating Germany.',
			),
			array(
				'question' => 'Is TEN a recruitment agency or job marketplace?',
				'answer'   => 'No. TEN is not a recruitment agency, job board, ATS, or automated job marketplace. We focus on pathway understanding, routing and structured next steps.',
			),
			array(
				'question' => 'Does TEN make visa or residence decisions?',
				'answer'   => 'No. Only the competent official authorities make binding visa, residence and related official determinations.',
			),
			array(
				'question' => 'What is the difference between .org and .de?',
				'answer'   => 'theexpatnetwork.org owns journey explanation and orchestration. theexpatnetwork.de provides focused checkers, assessments, calculators and preparation tools.',
			),
			array(
				'question' => 'Does registering guarantee an opportunity or response?',
				'answer'   => 'No. Registration allows us to review your background manually and consider relevant pathway connections. It does not guarantee employment, introductions, or outcomes.',
			),
			array(
				'question' => 'Are organisations submitting an enquiry automatically partners?',
				'answer'   => 'No. Submitting an organisation enquiry initiates a manual review of your request. It does not create an automatic commercial or institutional partnership.',
			),
		);
		?>
		<section id="faq" class="ten-section ten-section--sand ten-anchor-section" aria-labelledby="ten-faq-title">
			<div class="ten-container ten-faq">
				<div class="ten-section-heading ten-section-heading--center" data-ten-reveal>
					<p class="ten-eyebrow">Questions &amp; Answers</p>
					<h2 id="ten-faq-title">Frequently Asked Questions</h2>
				</div>
				<div class="ten-faq__items" data-ten-reveal>
					<?php foreach ( $items as $item ) : ?>
						<details class="ten-faq__item">
							<summary><?php echo esc_html( $item['question'] ); ?></summary>
							<div class="ten-faq__answer"><p><?php echo esc_html( $item['answer'] ); ?></p></div>
						</details>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/** 14. Footer */
	private function render_footer( $privacy_url, $imprint_url ) {
		?>
		<footer class="ten-footer" role="contentinfo">
			<div class="ten-container ten-footer__inner">
				<div class="ten-footer__grid">
					<div class="ten-footer__col">
						<span class="ten-footer__title">The Expat Network</span>
						<p class="ten-footer__text">Orientation &amp; Pathway Orchestration for Germany.</p>
						<p class="ten-footer__text">&copy; <?php echo esc_html( (string) date( 'Y' ) ); ?> The Expat Network. All rights reserved.</p>
					</div>
					<div class="ten-footer__col">
						<strong>Pathways &amp; Routes</strong>
						<ul>
							<li><a href="/work-in-germany/">Work in Germany</a></li>
							<li><a href="/german-job-offer/">German Job Offer</a></li>
							<li><a href="/living-in-germany/">Living in Germany</a></li>
							<li><a href="/for-organisations/">For Organisations</a></li>
						</ul>
					</div>
					<div class="ten-footer__col">
						<strong>Resources</strong>
						<ul>
							<li><a href="/pathways/">Germany Pathways</a></li>
							<li><a href="/official-sources/">Official Sources</a></li>
							<li><a href="/india-germany/">India &harr; Germany</a></li>
							<li><a href="https://theexpatnetwork.de/" target="_blank" rel="noopener noreferrer">Germany Tools (.de)</a></li>
						</ul>
					</div>
					<div class="ten-footer__col">
						<strong>Legal &amp; Trust</strong>
						<ul>
							<li><a href="/about/">About &amp; Trust</a></li>
							<li><a href="<?php echo esc_url( $privacy_url ); ?>">Privacy Policy</a></li>
							<li><a href="<?php echo esc_url( $imprint_url ); ?>">Imprint</a></li>
						</ul>
					</div>
				</div>
			</div>
		</footer>
		<?php
	}
}
